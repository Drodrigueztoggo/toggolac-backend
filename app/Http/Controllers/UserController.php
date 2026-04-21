<?php

namespace App\Http\Controllers;

use App\Exports\PersonalShopperExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddUserRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\CodeBondRegistry;
use App\Models\Bond;
use App\Models\User;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Http\Requests\CreateAvailabilityRequest;
use App\Mail\ActivationMail;
use App\Mail\ActivationSuccessMail;
use App\Mail\RegisterUserMail;
use App\Mail\ResetPasswordMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ShoppingCart;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{

    public function updateBankAccounts(Request $request, $id)
    {
        $user = User::find($id);
        if ($user === null) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        try {
            $validatedData = $request->validate([
                'bank_name' => 'required|string',
                'account_number' => 'required|string',
                'account_type_id' => [
                    'required',
                    Rule::exists('types_bank_accounts', 'id'),
                ],
                'routing_number' => 'nullable|string',
            ]);

            $user->update($validatedData);

            return new JsonResponse(['message' => 'Información bancaria actualizada con éxito'], 200);
        } catch (\Exception $e) {
            // dd($e);
            return new JsonResponse(['error' => 'Ha ocurrido un error al actualizar la información bancaria', 'message' => $e->getMessage()], 500);
        }
    }

    public function resetPasswordAndEmail(Request $request)
    {
        try {
            // Validar el correo electrónico proporcionado
            $this->validate($request, [
                'email' => 'required|email',
            ]);

            // Encuentra el usuario por su correo electrónico
            $user = User::where('email', $request->input('email'))->first();

            if (!$user) {
                // Manejar el caso en el que el usuario no existe
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró ningún usuario con el correo electrónico proporcionado.',
                ], 404);
            }

            // Genera una contraseña aleatoria segura
            $password = Str::random(12);

            // Actualiza la contraseña del usuario en la base de datos
            $user->password = Hash::make($password);
            $user->save();

            // Envía la nueva contraseña por correo electrónico
            Mail::to($user->email)->send(new ResetPasswordMail($user, $password));

            return response()->json([
                'status' => 'success',
                'message' => 'Se ha enviado una nueva contraseña al correo electrónico del usuario "' . $user->email . '".'
            ]);
        } catch (ValidationException $e) {
            // Manejar la excepción de validación
            return response()->json([
                'status' => 'error',
                'message' => 'El correo electrónico proporcionado no es válido.',
            ], 400);
        } catch (\Exception $e) {
            dd($e);
            // Manejar otras excepciones
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar la solicitud.',
            ], 500);
        }
    }


    public function getUsers(Request $request)
{
    try {
        $perPage    = $request->query('per_page', 20);
        $noPaginate = $request->filled('no_paginate');
        $roleId     = $request->role_id;
        $shStart    = $request->sh_filter_start_date;
        $shEnd      = $request->sh_filter_end_date;

        $query = User::select([
            'id', 'role_id', 'image_user', 'name', 'email',
            'email_verified_at', 'last_name', 'personal_id',
            'driver_license_number', 'phone_number', 'address',
            'birth_date', 'country_id', 'state_id', 'city_id',
            'postal_code', 'gender', 'bank_name', 'account_number',
            'account_type_id', 'routing_number', 'is_premium', 'created_at'
        ]);

        // --- Filters ---
        $query->when($roleId, fn($q, $v) => $q->where('role_id', $v))
              ->when($request->name, fn($q, $v) => $q->where('name', 'like', "%$v%"))
              ->when($request->email, fn($q, $v) => $q->where('email', 'like', "%$v%"))
              ->when($request->country_id, fn($q, $v) => $q->where('country_id', $v))
              ->when($request->is_premium, fn($q) => $q->where('is_premium', true))
              ->when($request->city_name, fn($q, $v) => $q->whereHas('cityInfo', fn($q) =>
                  $q->where('name', 'like', "%$v%")
              ));

        // Age filter
        if ($request->min_age && $request->max_age) {
            $query->whereBetween('birth_date', [
                now()->subYears($request->max_age)->format('Y-m-d'),
                now()->subYears($request->min_age)->format('Y-m-d'),
            ]);
        } elseif ($request->min_age) {
            $query->where('birth_date', '<=', now()->subYears($request->min_age)->format('Y-m-d'));
        } elseif ($request->max_age) {
            $query->where('birth_date', '>=', now()->subYears($request->max_age)->format('Y-m-d'));
        }

        // --- Eager load only what's needed ---
        $query->with([
            'countryInfo:id,name',
            'cityInfo:id,name',
            'accountBank',
            'evaluations:id,user_id,rating',
        ]);

        // --- Aggregates done in DB not PHP ---
        $query->withCount('ordersCompletes')
              ->withSum('ordersCompletes.purchaseOrderDetails as orders_completes_total', 'price');

        // Personal Shopper specific aggregates
        if ((int)$roleId === 2) {
            $dateFilter = fn($q) => $q->when(
                $shStart && $shEnd,
                fn($q) => $q->whereBetween('created_at', [$shStart, $shEnd])
            );

            $query->withCount([
                'ordersCompletesShopper as orders_completes_shopper_count' => fn($q) => $q->when(
                    $shStart && $shEnd,
                    fn($q) => $q->whereBetween('created_at', [$shStart, $shEnd])
                )
            ])
            ->withSum([
                'ordersCompletesShopper.purchaseOrderDetails as shopper_total' => fn($q) => $q->when(
                    $shStart && $shEnd,
                    fn($q) => $q->whereBetween('created_at', [$shStart, $shEnd])
                )
            ], 'price')
            ->withCount(['commissions as total_commissions' => $dateFilter])
            ->withSum(['commissions as commissions_sum_amount' => $dateFilter], 'amount');

            if ($request->sh_purchase_min) {
                $query->having('orders_completes_shopper_count', '>=', $request->sh_purchase_min);
            }
            if ($request->sh_purchase_max) {
                $query->having('orders_completes_shopper_count', '<=', $request->sh_purchase_max);
            }
        } else {
            $query->withCount('ordersCompletesShopper')
                  ->withSum('ordersCompletesShopper.purchaseOrderDetails as shopper_total', 'price');
        }

        $query->orderBy('created_at', 'desc');

        // --- Execute ---
        $users = $noPaginate
            ? $query->get()
            : $query->paginate($perPage);

        $collection = $noPaginate ? $users : $users->getCollection();
        $formatted  = $collection->map(fn($user) => $this->formatUser($user));

        if ($noPaginate) {
            return $formatted;
        }

        return [
            'data'          => $formatted,
            'current_page'  => $users->currentPage(),
            'per_page'      => $users->perPage(),
            'total'         => $users->total(),
            'last_page'     => $users->lastPage(),
            'next_page_url' => $users->nextPageUrl(),
            'prev_page_url' => $users->previousPageUrl(),
            'from'          => $users->firstItem(),
            'to'            => $users->lastItem(),
        ];

    } catch (Exception $e) {
        report($e);
        return response()->json([
            'status'  => 'error',
            'message' => 'Server error'
        ], 500);
    }
}

private function formatUser(User $user): array
{
    $shopperCount = $user->orders_completes_shopper_count ?? 0;
    $shopperTotal = $user->shopper_total ?? 0;
    $rating       = $user->evaluations?->avg('rating') ?? 0;

    return [
        'id'                                => $user->id,
        'role_id'                           => $user->role_id,
        'image_user'                        => $user->image_user,
        'name'                              => $user->name,
        'email'                             => $user->email,
        'email_verified_at'                 => $user->email_verified_at,
        'last_name'                         => $user->last_name,
        'personal_id'                       => $user->personal_id,
        'driver_license_number'             => $user->driver_license_number,
        'phone_number'                      => $user->phone_number,
        'address'                           => $user->address,
        'birth_date'                        => $user->birth_date,
        'country_id'                        => $user->country_id,
        'state_id'                          => $user->state_id,
        'city_id'                           => $user->city_id,
        'postal_code'                       => $user->postal_code,
        'gender'                            => $user->gender,
        'bank_name'                         => $user->bank_name,
        'account_number'                    => $user->account_number,
        'account_type_id'                   => $user->account_type_id,
        'routing_number'                    => $user->routing_number,
        'is_premium'                        => $user->is_premium,
        'created_at'                        => $user->created_at,
        'rating'                            => round($rating, 1),
        'country_info'                      => $user->countryInfo,
        'city_info'                         => $user->cityInfo,
        'account_bank'                      => $user->accountBank,
        'orders_completes_count'            => $user->orders_completes_count ?? 0,
        'orders_completes_total'            => MoneyConvert::USD($user->orders_completes_total ?? 0),
        'sh_orders_completes_shopper_count' => $shopperCount,
        'sh_average_sales'                  => MoneyConvert::USD(
                                                $shopperCount > 0
                                                ? round($shopperTotal / $shopperCount, 2)
                                                : 0
                                              ),
        'sh_commissions_sum_amount'         => MoneyConvert::USD($user->commissions_sum_amount ?? 0),
        'sh_total_commissions'              => $user->total_commissions ?? 0,
    ];
}

    public function downloadUsersExcel(Request $request)
{
    $startDate = $request->query('start_date');
    $endDate   = $request->query('end_date');

    // Stream export using Laravel Excel chunk reading
    // Never loads entire dataset into memory
    return Excel::download(
        new UsersExport($startDate, $endDate),
        'users_' . now()->format('Y-m-d') . '.xlsx'
    );
}

    public function downloadPersonalShopperExcel(Request $request)
    {
        try {

            $filter_start_date = $request->start_date;
            $filter_end_date = $request->end_date;

            $requestFunctionGetUsers = new Request([
                'role_id' => 2,
                'no_paginate' => true,
                'sh_filter_start_date' => $filter_start_date,
                'sh_filter_end_date' => $filter_end_date,
            ]);
            $data = $this->getUsers($requestFunctionGetUsers);


            return Excel::download(new PersonalShopperExport($data), 'personal-shopper.xlsx'); // Nombre del archivo Excel


        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    public function addUser(AddUserRequest $request)
    {
        try {
            DB::beginTransaction();

            $userData = $request->all();

            // Genera un token de activación único
            $activationToken = Str::random(60);
            $userData['activation_token'] = $activationToken;


            // Hash the password
            $userData['password'] = Hash::make($request->password);
            // $userData['role_id'] = 1;

            if ($request->hasFile('image_user')) {
                $imagePath = $this->storeImage($request->file('image_user'));
                $userData['image_user'] = $imagePath;
            }

            $user = User::create($userData);

            if ($user['role_id'] == 2) {
                // si es role 2 agregamos la disponibilidad 
                $data = [
                    'user_id' => $user->id,
                    'availability' => [
                        [
                            'day' => 'Monday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ],
                        [
                            'day' => 'Tuesday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ],
                        [
                            'day' => 'Wednesday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ],
                        [
                            'day' => 'Thursday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ],
                        [
                            'day' => 'Friday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ],
                        [
                            'day' => 'Saturday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ],
                        [
                            'day' => 'Sunday',
                            'start_time' => '08:00:00',
                            'end_time' => '17:00:00'
                        ]
                    ]
                ];
                $availabilityController = new AvailabilityPersonalShopperController();
                $response = $availabilityController->create(new CreateAvailabilityRequest($data));
            }

            $credentials = [
                'email' => $userData['email'],
                'password' => $request->password,
            ];

            try {
                Mail::to($userData['email'])->send(new ActivationMail($request->name, $userData['activation_token']));
            } catch (\Exception $e) {
                //throw $th;
            }

            if ($user['role_id'] == 3) {
                try {
                    $code = Str::random(5);
                    $bond = new Bond();
                    $bond->code = $code;
                    $bond->name = "PRIMERACOMPRA-$code";
                    $bond->minimun_amount = 300000;
                    $bond->first_purchse = true;
                    $bond->value_bond = 80000;
                    $bond->save();
                    Mail::to($userData['email'])->send(new CodeBondRegistry($request->name, $bond->name));
                } catch (\Exception $e) {
                    //throw $th;
                }
            }

            DB::commit(); // Confirmar la transacción

            return response()->json([
                'status' => 'success',
                'message' => 'Se ha enviado un correo de activación a tu dirección de correo electrónico. Por favor, verifica tu correo para activar tu cuenta.',
                'data' => $user
            ], 201);
        } catch (Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error


            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showUser(Request $request)
    {
        try {

            $id = $request->id;

            $user = User::select(
                'id',
                'role_id',
                'image_user',
                'name',
                'email',
                'email_verified_at',
                'last_name',
                'personal_id',
                'phone_number',
                'address',
                'birth_date',
                'country_id',
                'state_id',
                'city_id',
                'postal_code',
                'gender',
                'bank_name',
                'account_number',
                'account_type_id',
                'routing_number',
                'is_premium'
            )
                ->with('evaluations', 'countryInfo', 'stateInfo', 'cityInfo', 'accountBank')->findOrFail($id);


            $rating = isset($user->evaluations) && count($user->evaluations) > 0 ? $user->evaluations->avg('rating') : 0;

            $user['rating'] = $rating;


            return response()->json($user);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateUser(UpdateUserRequest $request)
    {
        try {

            $id = $request->id;
            $user = User::findOrFail($id);


            if ($request->hasFile('image_user')) {
                $this->deleteImage($user->image_user);
                $imagePath = $this->storeImage($request->file('image_user'));
            }

            $user->update($request->all());
            if (isset($imagePath)) {
                $user->image_user = $imagePath;
            }
            $user->save();

            return response()->json($user, 200);
        } catch (Exception $e) {

            if (!isset($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $this->deleteImage($user->image_user);
            $user->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];
        } catch (Exception $e) {

            if (!isset($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function activateAccount($token)
    {
        try {
            $user = User::where('activation_token', $token)->first();

            if (!$user) {
                return response()->json(['message' => 'Token inválido'], 400);
            }

            $user->activation_token = null;
            $user->is_active = true;
            $user->save();



            try {
                Mail::to($user->email)->send(new ActivationSuccessMail($user->name));
            } catch (\Exception $e) {
                //throw $th;
            }



            return response()->json(['message' => 'Cuenta activada exitosamente'], 200);
        } catch (\Exception $e) {
            //throw $th;
            return ["status" => 'error', 'message' => $e->getMessage()];
        }
    }
    private function storeImage($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/user_images/' . now()->format('Y-m-d'), $imageName, 'public');

        return $imagePath;
    }

    private function deleteImage($imagePath)
    {
        if ($imagePath && Storage::exists('public/' . $imagePath)) {
            Storage::delete('public/' . $imagePath);
        }
    }

    public function updateAddress(UpdateAddressRequest $request)
    {
        try {
            $id = $request->id;
            $user = User::findOrFail($id);

            $user->address = $request->address;
            $user->country_id = $request->country_id;
            $user->state_id = $request->state_id;
            $user->city_id = $request->city_id;
            $user->postal_code = $request->postal_code;
            $user->save();

            $query = ShoppingCart::with('product', 'product.mallProducts', 'product.categories', 'product.storeProducts')->where('user_id', $id)->get();
            $user->load(['countryInfo', 'stateInfo','cityInfo']);
            $data = [
                "user_id" => $id,
                "products" => [],
                "by" => "user",
                "delete" => true
            ];

            foreach ($query as $key => $item) {
                $data["products"][$key] = [
                    "malls" => $item->product->mallProducts,
                    "id" => $item->product_id,
                    "price_from" => $item->product->price_from,
                    "stores" => $item->product->storeProducts,
                    "categories" => $item->product->categories,
                    "image" => $item->image_product,
                    "weight" => $item->weight,
                    "comment" => $item->comment,
                    "quantity" => $item->quantity,
                ];
            }
            $newRequest = new Request($data);
            $shoppingController = new ShoppingCartController();
            $shoppingController->addProductsToCart($newRequest);

            return response()->json($user, 200);
        } catch (Exception $e) {

            if (!isset($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El usuario no se encuentra registrado.'
                ], 404);
            }
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
