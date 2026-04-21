<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\Carrier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Cknow\Money\Money as MoneyConvert;
use App\Http\Requests\CreatePurchaseOrderRequest;

class ShoppingCartController extends Controller
{
    public function addProductsToCart(Request $request)
    {
        try {
            DB::beginTransaction();
            $currency = $request->query('currency');
            $data = $request->all();
            $query = User::findOrFail($data['user_id']);
            $carrie = Carrier::where('country_id','=',$query->country_id)->first();
            $currencyFunctions = new CurrencyController();

            $generatePurchase = [
                "id" => "",
                "client_id" => $data['user_id'],
                "mall_id" => "",
                "personal_shopper_id" => 21,
                "destination_address"=> $query->address,
                "destination_country_id" => $query->country_id,
                "destination_state_id" => $query->state_id,
                "destination_city_id" => $query->city_id,
                "destination_postal_code" => $query->postal_code ?? null,
                "conveyor_id" => $carrie->id ?? "",
                "estimated_date" =>"",
                "details"=>[],
                "currency" =>$currency
            ];

            // Obtener el usuario y el carrito de compras
            $user_id = $data['user_id'];
            $by = $data['by'];
            $delete = $data['delete'];

            if (isset($delete) && $delete) {
                //Eliminamos todos los productos actuales en el carrito
                ShoppingCart::where('user_id', $user_id)->delete();
            }

            
            foreach ($data['products'] as $key => $product) {
                // Use withTrashed() so that products which were soft-deleted
                // prematurely (before payment confirmation) can still be found
                // when a customer retries a failed payment.
                $queryProducto = Product::withTrashed()->findOrFail($product['id']);

                // Block if another user has this product actively reserved
                $reservedByOther = ShoppingCart::where('product_id', $product['id'])
                    ->where('user_id', '!=', $user_id)
                    ->where('reserved_at', '>', now()->subMinutes(10))
                    ->exists();
                if ($reservedByOther) {
                    DB::rollBack();
                    return response()->json(['message' => 'Este producto acaba de ser reservado por otro comprador. Inténtalo en unos minutos.'], 409);
                }

                // If the product was soft-deleted, restore it — the payment hasn't
                // been confirmed yet so it should remain visible on the storefront.
                if ($queryProducto->trashed()) {
                    $queryProducto->restore();
                }

                $product_id = $product['id'];
                $quantity   = $product['quantity'];
                $comment    = $product['comment'] ?? "";

                // ── mall_id: prefer data from request, fall back to DB ──────────
                if (!empty($product['malls'][0]['id'])) {
                    $generatePurchase['mall_id'] = $product['malls'][0]['id'];
                } elseif (!empty($product['mall_products'][0]['id'])) {
                    $generatePurchase['mall_id'] = $product['mall_products'][0]['id'];
                } else {
                    $dbMall = $queryProducto->mallProducts()->first();
                    $generatePurchase['mall_id'] = $dbMall ? $dbMall->id : null;
                }

                // ── store_id: prefer request data, fall back to DB ──────────────
                if (!empty($product['stores'][0]['id'])) {
                    $storeId = $product['stores'][0]['id'];
                } elseif (!empty($product['store']['id'])) {
                    $storeId = $product['store']['id'];
                } else {
                    $dbStore = $queryProducto->storeProducts()->first();
                    $storeId = $dbStore ? $dbStore->id : "";
                }

                // ── category_id: prefer request data, fall back to DB ───────────
                if (!empty($product['categories'][0]['id'])) {
                    $categoryId = $product['categories'][0]['id'];
                } else {
                    $dbCategory = $queryProducto->categories()->first();
                    $categoryId = $dbCategory ? $dbCategory->id : null;
                }

                $generatePurchase['details'][$key] = [
                    "product_id"  => $product_id,
                    "price"       => $queryProducto->price_from,
                    "store_id"    => $storeId,
                    "amount"      => $quantity,
                    "category_id" => $categoryId,
                    "image"       => $product['image'] ?? $queryProducto->image,
                    // weight: always prefer the live DB value to avoid stale/missing data
                    "weight"      => $queryProducto->weight ?? ($product['weight'] ?? 0),
                    "comment"     => $comment,
                ];

                // Insertar el producto en el carrito
                ShoppingCart::create([
                    'user_id'     => $user_id,
                    'product_id'  => $product_id,
                    'quantity'    => $quantity,
                    'comment'     => $comment,
                    'by'          => $by,
                    'reserved_at' => now(),
                ]);
            }

            $newRequest = new Request($generatePurchase);
            $purchaseOrder = new PurchaseOrderController();
            $purchaseOrder->addPurchaseOrderFromShopCar($newRequest);

            DB::commit();

            return response()->json(['message' => 'Tu carrito de compras a sido modificado.!'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('addProductsToCart error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => basename($e->getFile())], 500);
        }
    }

    public function updateQuantity(Request $request)
    {
        try {
            $cart_id = $request->input('cart_id');
            $new_quantity = $request->input('quantity');

            // Verificar si el registro del carrito existe
            $cartItem = ShoppingCart::find($cart_id);

            if (!$cartItem) {
                return response()->json(['message' => 'El registro del carrito no se encontró'], 404);
            }

            // Validar que la nueva cantidad sea mayor que cero
            if ($new_quantity <= 0) {
                return response()->json(['message' => 'La cantidad debe ser mayor que cero'], 400);
            }

            // Actualizar la cantidad del producto en el carrito
            $cartItem->quantity = $new_quantity;
            $cartItem->save();

            return response()->json(['message' => 'Cantidad actualizada con éxito'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la cantidad'], 500);
        }
    }

    public function removeFromCart(Request $request)
    {
        try {
            $cart_id = $request->input('cart_id');

            // Verificar si el registro del carrito existe
            $cartItem = ShoppingCart::find($cart_id);

            if (!$cartItem) {
                return response()->json(['message' => 'El registro del carrito no se encontró'], 404);
            }

            // Eliminar el registro del carrito
            $cartItem->delete();

            return response()->json(['message' => 'Producto eliminado del carrito con éxito'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el producto del carrito'], 500);
        }
    }


    public function seeUserCartAdmin(Request $request)
    {
        try {

            $requestFunction = new Request([
                'user_id' => $request->query('user_id'),
                'email_user' => $request->query('email_user'),
                'currency' => $request->query('currency'),
            ]);


            $responseCart = json_decode($this->userCart($requestFunction), true);

            // Verifica si hay al menos un producto
            if (count($responseCart) > 0) {
                // Obtén los "malls" del primer producto
                $firstProductMalls = collect($responseCart[0]['product']['malls'])->pluck('id')->toArray();

                // Encuentra la intersección de "malls" entre todos los productos
                $commonMalls = array_reduce($responseCart, function ($carry, $product) use ($firstProductMalls) {
                    $productMalls = collect($product['product']['malls'])->pluck('id')->toArray();
                    return array_intersect($carry, $productMalls);
                }, $firstProductMalls);

                // Convierte los "malls" comunes en un arreglo asociativo
                $commonMalls = array_values($commonMalls);
            } else {
                $commonMalls = [];
            }

            $infoMalls = [];
            $infoMallsFormat = [];
            if (count($commonMalls) > 0) {
                $infoMalls = Mall::whereIn('id', $commonMalls)->select('id', 'name_mall')->get();
                $infoMallsFormat = $infoMalls->map(function ($mall) {

                    return [
                        'id' => $mall->id,
                        'name' => $mall->name_mall
                    ];
                });
            }

            return response()->json(['malls' => $infoMallsFormat, 'cart_user' => $responseCart]);
        } catch (\Exception $e) {
            //throw $th;
            dd($e);
            return response()->json(['message' => 'Error al ver el carrito'], 500);
        }
    }

    public function seeUserCart(Request $request)
    {
        try {

            $this->validate($request, [
                'email_user' => [
                    'nullable',
                    'email',
                    function ($attribute, $value, $fail) {
                        if (!empty($value) && !User::where('email', $value)->exists()) {
                            $fail("El correo electrónico no está registrado en la tabla 'users'.");
                        }
                    },
                ],
                'user_id' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        if (!empty($value) && !User::where('id', $value)->exists()) {
                            $fail("El ID de usuario no está registrado en la tabla 'users'.");
                        }
                    },
                ],
            ]);

            // Resto de la lógica de tu función



            $requestFunction = new Request([
                'user_id' => $request->user_id,
                'email_user' => $request->query('email_user'),
                'currency' => $request->query('currency'),
            ]);


            $responseCart = json_decode($this->userCart($requestFunction), true);


            // Verifica si hay al menos un producto
            if (count($responseCart) > 0) {
                // Obtén los "malls" del primer producto
                $firstProductMalls = collect($responseCart[0]['product']['malls'])->pluck('id')->toArray();

                // Encuentra la intersección de "malls" entre todos los productos
                $commonMalls = array_reduce($responseCart, function ($carry, $product) use ($firstProductMalls) {
                    $productMalls = collect($product['product']['malls'])->pluck('id')->toArray();
                    return array_intersect($carry, $productMalls);
                }, $firstProductMalls);

                // Convierte los "malls" comunes en un arreglo asociativo
                $commonMalls = array_values($commonMalls);
            } else {
                $commonMalls = [];
            }

            $infoMalls = [];
            $infoMallsFormat = [];
            if (count($commonMalls) > 0) {
                $infoMalls = Mall::whereIn('id', $commonMalls)->select('id', 'name_mall')->get();
                $infoMallsFormat = $infoMalls->map(function ($mall) {
                    return [
                        'id' => $mall->id,
                        'name' => $mall->name_mall
                    ];
                });
            }

            $user_id = $request->query('user_id');

            // Refresh reservation timestamp so the 10-min window resets on each cart view
            if ($user_id) {
                ShoppingCart::where('user_id', $user_id)->update(['reserved_at' => now()]);
            }

            if (isset($user_id) || isset($responseCart[0])) {
                $requestFunctionPurchase = new Request([
                    'client_id' => $user_id ? $user_id : $responseCart[0]['user']['id'],
                    'purchase_status_id' => 1, //SOLO LAS ORDENES DE COMPRA QUE ESTEN "PENDIENTE" 
                    'no_paginate' => true,
                    'currency' => $request->query('currency'),
                    'TGGlanguage' => $request->query('TGGlanguage'),
                ]);

                $purchaseOrder = new PurchaseOrderController();
                $purchaseOrderDetail = $purchaseOrder->getPurchaseOrder($requestFunctionPurchase);

                //  return $purchaseOrderDetail;

                return response()->json(['malls' => $infoMallsFormat, 'cart_user' => $responseCart, 'purchase_detail' => isset($purchaseOrderDetail) ? $purchaseOrderDetail : null]);
            }else{
                return response()->json(['malls' => null, 'cart_user' => null, 'purchase_detail' => null], 200);
            }
        } catch (\Exception $e) {
            //throw $th;
            dd($e);
            return response()->json(['message' => $e->getLine()], 500);
        }
    }

    public function userCart(Request $request)
    {
        try {

            $user_id = $request->query('user_id');
            $email_user = $request->query('email_user');

            $TGGlanguage = $request->TGGlanguage;
            $currency = $request->currency;

            $translate = new GoogleTranslateController();
            $currencyFunctions = new CurrencyController();


            $cartProductsQuery = ShoppingCart::with('user')->whereHas('product', function ($query) {
                // Filtrar las compras en función del estado
                $query->whereNull('deleted_at');
            });



            if ($email_user) {
                $cartProductsQuery->whereHas('user', function ($query) use ($email_user) {
                    $query->where('email', $email_user);
                });
            }
            if ($user_id) {
                $cartProductsQuery->where('user_id', $user_id);
            }


            $cartProducts = $cartProductsQuery->get();


            $formattedProducts = $cartProducts->map(function ($product) use ($currency, $currencyFunctions) {

                return [
                    'id' => $product->id,
                    'user' => $product->user,
                    'is_purchase_order' => $product->is_purchase_order,
                    'quantity' => $product->quantity,
                    'comment' => $product->comment,
                    'product' => [
                        'id' => $product->product->id,
                        'weight' => $product->product->weight,
                        'brand' => isset($product->product->brand) ? [
                            'id' => $product->product->brand->id,
                            'name' => $product->product->brand->name_brand,
                            'image' => $product->product->brand->image
                        ] : null,
                        'price' => [
                            'min' => isset($product->product->price_from) ? $product->product->price_from : 0, 
                            'max' => isset($product->product->price_to) ? $product->product->price_to : 0, 
                        ],
                        'name' => $product->product->name,
                        'image' => $product->product->image,
                        'malls' => $product->product->mallProducts,
                        'categories' => $product->product->categories,
                        'stores' => $product->product->storeProducts,
                        'quantity' => $product->quantity,
                        'comment' => $product->comment,
                    ],
                ];
            });

            return $formattedProducts;
        } catch (\Exception $e) {
            //throw $th;    
            // dd($e);
            return response()->json(['message' => 'Error al ver el carrito'], 500);
        }
    }
}
