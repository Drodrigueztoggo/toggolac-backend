<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnProductRequest;
use App\Mail\ReturnsProductsMail;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseReturnAnswer;
use App\Models\PurchaseReturnAnswerImage;
use App\Models\PurchaseReturnProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Support\Facades\Mail;

class ReturnsController extends Controller
{

    public function sendCommentReturn(Request $request)
    {

        try {
            // 
            DB::beginTransaction();
            $this->validate($request, [
                'purchase_return_id' => 'required|integer',
                'comment' => 'required',
                'images.*' => 'nullable|file|mimes:webp,jpeg,jpg,png,gif,bmp|max:500',
            ]);

            $createUserId = auth()->user()->id;


            $purchase_return_id = $request->purchase_return_id;
            $comment = $request->comment;
            $images = $request->images;


            $infoUser = PurchaseReturnProduct::with(['detailProduct:id,purchase_order_header_id,product_id', 'detailProduct.purchaseOrderHeader:id,client_id', 'detailProduct.product:name_product,id', 'detailProduct.purchaseOrderHeader.client' => function ($query) {
                $query->select('id', 'name', 'email'); // Lista los campos que deseas seleccionar
            }])->select('id', 'purchase_order_detail_id')->find($purchase_return_id);


            $newAnswer = new PurchaseReturnAnswer();
            $newAnswer->purchase_return_product_id =  $purchase_return_id;
            $newAnswer->comment = $comment;
            $newAnswer->user_id = $createUserId;
            $newAnswer->save();


            $updateStatusReturnProduct = PurchaseReturnProduct::where('id', $purchase_return_id)->update([
                "return_status" => 'En progreso'
            ]);




            if (isset($infoUser) && isset($infoUser->detailProduct->purchaseOrderHeader->client)) {
                $email = $infoUser->detailProduct->purchaseOrderHeader->client->email;
                $name = $infoUser->detailProduct->purchaseOrderHeader->client->name;

                $imagePathsResponse = null;




                if (isset($images)) {
                    foreach ($images as $image) {
                        $imagePath = $this->storeImageReturns($image); // Utiliza tu función storeImage para almacenar imágenes
                        $imagePathsResponse[] = $imagePath;

                        $newAnswerImages = new PurchaseReturnAnswerImage();
                        $newAnswerImages->purchase_return_answer_id = $newAnswer->id;
                        $newAnswerImages->image_answer = $imagePath;
                        $newAnswerImages->save();
                    }
                }

                // return $imagePathsResponse;


                $mail = new ReturnsProductsMail($comment, isset($infoUser->detailProduct->product) ? $infoUser->detailProduct->product->name_product : null, $name);

                foreach ($imagePathsResponse as $image) {
                    $path = storage_path('app/public/' . $image);

                    if (file_exists($path)) {
                        $mail->attach($path);
                    }
                }

                Mail::to($email)->send($mail);



                DB::commit();

                return ['status' => 'success'];
            } else {
                DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se ha encontrado el cliente',
                ], 404);
            }

            return $infoUser;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 404);
        }
    }

    public function getReturnProduct(Request $request)
    {

        try {

            $perPage = $request->query('per_page', 20);


            $filter_order_id = $request->query('order_id');
            $filter_client_id = $request->query('client_id');
            $filter_personal_shopper_id = $request->query('personal_shopper_id');
            $filter_date = $request->query('date');


            $returnsQuery = PurchaseReturnProduct::with(
                'detailProduct.product.brand',
                'detailProduct.product.categories',
                'detailProduct.store.mallInfo.city',
                'detailProduct.store.mallInfo.countryInfo',
                'detailProduct.purchaseOrderHeader.purchaseStatus',
                'detailProduct.purchaseOrderHeader.store',
                'detailProduct.purchaseOrderHeader.personalShopper',
                'detailProduct.purchaseOrderHeader.client',
            );

            if ($filter_order_id) {
                $returnsQuery->whereHas('detailProduct.purchaseOrderHeader', function ($query) use ($filter_order_id) {
                    $query->where('purchase_order_headers.id', $filter_order_id);
                });
            }
            if ($filter_client_id) {
                $returnsQuery->whereHas('detailProduct.purchaseOrderHeader', function ($query) use ($filter_client_id) {
                    $query->where('purchase_order_headers.client_id', $filter_client_id);
                });
            }
            if ($filter_personal_shopper_id) {
                $returnsQuery->whereHas('detailProduct.purchaseOrderHeader', function ($query) use ($filter_personal_shopper_id) {
                    $query->where('purchase_order_headers.personal_shopper_id', $filter_personal_shopper_id);
                });
            }
            if ($filter_date) {
                $returnsQuery->whereDate('purchase_return_products.created_at', Carbon::parse($filter_date)->format('Y-m-d'));
            }


            $returns = $returnsQuery->orderBy('created_at', 'desc')->paginate($perPage);


            $returnsFormat = $returns->map(function ($return) {
                return [
                    "id" => $return->id, //ID DE LA DEVOLUCION
                    "purchase_order_detail_id" => $return->purchase_order_detail_id, //ID DEL DETALLE DE LA ORDEN
                    "return_reason" => $return->return_reason,
                    "amount" => $return->amount, //CANTIDAD DEVUELTOS
                    "return_status" => $return->return_status, //ESTADO DE LA DEVOLUCION
                    "comment_shopper" => $return->comment_shopper, //COMENTARIO GENERADO
                    "created_at" =>  Carbon::parse($return->created_at)->format('Y-m-d H:i:s'), //FECHA DE LA DEVOLUCION
                    "images" => $return->images,
                    "detail_product" => isset($return->detailProduct) ?
                        [
                            "id" => $return->detailProduct->id,
                            "product" => $return->detailProduct->product,
                            "order_id" => $return->detailProduct->purchase_order_header_id, //ID DE LA ORDEN
                            "product_id" => $return->detailProduct->product_id,
                            "price" => MoneyConvert::USD($return->detailProduct->price),
                            "amount" => $return->detailProduct->amount,
                            "weight" => $return->detailProduct->weight,
                            "store" => $return->detailProduct->store,
                            "country" => isset($return->detailProduct->store->mallInfo->countryInfo) ? $return->detailProduct->store->mallInfo->countryInfo : null,
                            "city" => isset($return->detailProduct->store->mallInfo->city) ? $return->detailProduct->store->mallInfo->city : null,
                            "purchase_order_header" => isset($return->detailProduct->purchaseOrderHeader) ? [
                                "id" => $return->detailProduct->purchaseOrderHeader->id, //ID DE LA ORDEN
                                "purchase_status" => $return->detailProduct->purchaseOrderHeader->purchaseStatus,
                                "created_at" => Carbon::parse($return->detailProduct->purchaseOrderHeader->created_at)->format('Y-m-d H:i:s'),
                                "return_status" => $return->detailProduct->purchaseOrderHeader->return_status,
                                "personal_shopper" => isset($return->detailProduct->purchaseOrderHeader->personalShopper) ? [
                                    "id" => $return->detailProduct->purchaseOrderHeader->personalShopper->id,
                                    "name" => $return->detailProduct->purchaseOrderHeader->personalShopper->name,
                                ] : null,
                                "client" => isset($return->detailProduct->purchaseOrderHeader->client) ? [
                                    "id" => $return->detailProduct->purchaseOrderHeader->client->id,
                                    "name" => $return->detailProduct->purchaseOrderHeader->client->name,
                                ] : null,
                            ] : null,
                        ]
                        : null,

                ];
            });


            $response = [
                "data" => $returnsFormat,
                'current_page' => $returns->currentPage(),
                'first_page_url' => $returns->url(1),
                'from' => $returns->firstItem(),
                'last_page' => $returns->lastPage(),
                'last_page_url' => $returns->url($returns->lastPage()),
                'next_page_url' => $returns->nextPageUrl(),
                'path' => $returns->url($returns->currentPage()),
                'per_page' => $returns->perPage(),
                'prev_page_url' => $returns->previousPageUrl(),
                'to' => $returns->lastItem(),
                'total' => $returns->total(),
            ];

            return $response;
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción de base de datos

            dd($e);
            // Manejar el error según tus necesidades

        }
    }




    public function updateStatusReturnProduct(Request $request)
    {
        // Iniciar una transacción de base de datos

        try {
            DB::beginTransaction();

            $request = $request->all();
            $id = $request['id'];
            $return_status = $request['return_status'];
            $comment_shopper = $request['comment_shopper'];
            $purchase_order_id = $request['purchase_order_id'];


            $updateReturnProduct = PurchaseReturnProduct::where('id', $id)->update([
                "return_status" => $return_status,
                "comment_shopper" => $comment_shopper,
            ]);



            //OBTENEMOS TODOS LOS IDS DE DEL DETALLE DE LA ORDEN, PARA VALIDAR CUANTOS DE ESTOS ESTAN POR DEVOLUCION Y SI YA TODOS ESTAN COMPLETADOS SE COMPLETA LA DEVOLUCION EN LA ORDEN 
            $getIdDetailsOrder = PurchaseOrderDetail::where('purchase_order_header_id', $purchase_order_id)->pluck('id')->toArray();
            if (isset($getIdDetailsOrder) && count($getIdDetailsOrder) > 0) {
                $validarDevoluciones =  PurchaseReturnProduct::whereIn('purchase_order_detail_id', $getIdDetailsOrder)->whereIn('return_status', ['Pendiente por revisión', 'En proceso'])->count();
                if ($validarDevoluciones > 0) {
                    //SE ACTUALIZA EL ESTADO DE LA ORDEN A EN PROCESO
                    $updateReturnOrder = PurchaseOrderHeader::where('id', $purchase_order_id)->update([
                        "return_status" => "En proceso"
                    ]);
                } else {
                    $updateReturnOrder = PurchaseOrderHeader::where('id', $purchase_order_id)->update([
                        "return_status" => "Completado"
                    ]);
                }
            }


            DB::commit();

            return ['status' => 'success'];
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción de base de datos
            DB::rollback();
            dd($e);
        }
    }


    public function saveReturnProduct(ReturnProductRequest $request)
    {
        // Iniciar una transacción de base de datos
        DB::beginTransaction();

        try {
            // Actualizar la orden principal en el modelo PurchaseOrderHeader
            $purchaseOrderHeader = PurchaseOrderHeader::find($request->purchase_id);
            if (!$purchaseOrderHeader) {
                throw new \Exception("Orden de compra no encontrada");
            } else if ($purchaseOrderHeader->purchase_status_id != 2 && $purchaseOrderHeader->purchase_status_id != 4) {
                return response()->json([
                    'status' => 'No se puede genenar una devoluvión',
                ], 403);
            } else if ($purchaseOrderHeader->purchase_status_id == 4) {
                return response()->json([
                    'status' => 'No se puede genenar una devoluvión, ya hay una devolución en proceso.',
                ], 403);
            }

            $purchaseOrderHeader->purchase_status_id = '4'; //Devolución
            $purchaseOrderHeader->return_status = 'Pendiente por revisión';
            $purchaseOrderHeader->save();

            foreach ($request->products as $key => $productoDetail) {
                // Crear una nueva entrada en el modelo PurchaseReturnProduct
                $returnProduct = new PurchaseReturnProduct([
                    'purchase_order_detail_id' => $productoDetail['purchase_order_detail_id'],
                    'return_reason' => $productoDetail['return_reason'],
                    'amount' => $productoDetail['amount'],
                    'return_status' => 'Pendiente por revisión', // Guardar el estado como 'Pendiente por revisión'
                ]);
                $returnProduct->save();

                // Guardar las imágenes (si es necesario)
                $imagePaths = [];

                // return gettype($productoDetail['return_images']);

                if (isset($productoDetail['return_images'])) {
                    foreach ($productoDetail['return_images'] as $image) {
                        $imagePath = $this->storeImage($image); // Utiliza tu función storeImage para almacenar imágenes
                        $imagePaths[] = $imagePath;
                    }
                    $returnProduct->return_images = $imagePaths; // Guardar las rutas de las imágenes en la base de datos
                    $returnProduct->save();
                }
            }



            // Confirmar la transacción de base de datos
            DB::commit();

            return response()->json([
                'status' => 'success',
            ], 200);
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción de base de datos
            DB::rollback();

            dd($e);
            // Manejar el error según tus necesidades

        }
    }

    private function storeImage($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/return_product_images/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }
    private function storeImageReturns($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/return_product_images_response/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }
}
