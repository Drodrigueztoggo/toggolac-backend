<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceptionCenterRequest;
use App\Models\Commission;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseOrderHeaderLog;
use App\Models\ReceptionCenter;
use Exception;
use Illuminate\Http\Request;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceptionCenterController extends Controller
{

    public function getReceptionCenter(Request $request)
    {
        try {
            $currencyFunctions = new CurrencyController();

            $per_gage = $request->query('per_page', 20);
            $personal_shopper_id = $request->query('personal_shopper_id');
            $id_orden = $request->query('id');



            $allOrders = PurchaseOrderHeader::with(
                'receptionCenter.infoProduct.product',
                'purchaseStatus',
                'infoTransaction',
                'personalShopper'
            );

            if(isset($personal_shopper_id)){
                $allOrders->where('personal_shopper_id', $personal_shopper_id);
            }
            if(isset($id_orden)){
                $allOrders->where('id', $id_orden);
            }

            $allOrders->whereHas('receptionCenter', function ($query) {
                $query->whereNotNull('purchase_product_id');
            });


            $allOrders = $allOrders->orderBy('created_at', 'desc')->paginate($per_gage);


            $allOrdersFormat = $allOrders->map(function ($order) use ($currencyFunctions) {

                $todosAceptados = collect($order->receptionCenter)->every(function ($item) {
                    return $item['status'] === 'Aceptado';
                });

                $receptionFormat = collect($order->receptionCenter)->map(function ($reception) use ($currencyFunctions) {

                    return [
                        "id" => $reception->id,
                        "status" => $reception->status,
                        "info_product" => isset($reception->infoProduct) ? [
                            "amount" => $reception->infoProduct->amount,
                            "weight" => $reception->infoProduct->weight,
                            "detail" => isset($reception->infoProduct->product) ? [
                                "id" => $reception->infoProduct->product->id,
                                "name" => $reception->infoProduct->product->name,
                                "image" => $reception->infoProduct->product->image,
                            ] : null,
                            "price" => $currencyFunctions->convertAmount('USD', "USD", $reception->infoProduct->price)
                        ] : null,
                        "optimal_conditions_product" => $reception->optimal_conditions_product == 1 ? true : false,
                        "verified_quantity" => $reception->verified_quantity == 1 ? true : false,
                        "conditions_brand" => $reception->conditions_brand == 1 ? true : false,
                        "invoice_order" => $reception->invoice_order == 1 ? true : false,

                    ];
                });

                return [
                    "id" => $order->id,
                    "status_Reception" => $todosAceptados,
                    "purchase_status" => $order->purchaseStatus,
                    "reception_info" => $receptionFormat,
                    "personal_shopper" => isset($order->personalShopper) ? [
                        "id" => $order->personalShopper->id,
                        "name" => $order->personalShopper->name,
                        "last_name" => $order->personalShopper->last_name,
                        "image" => $order->personalShopper->image,
                    ] : null,
                    "info_transaction" => isset($order->infoTransaction) ?
                        [
                            "id" => $order->infoTransaction->id,
                            "created_date" => $order->infoTransaction->created_date,
                            "approved_date" => $order->infoTransaction->approved_date,
                            "status" => $order->infoTransaction->status,
                            "amount" => $currencyFunctions->convertAmount('USD', "USD", $order->infoTransaction->amount),
                        ] : null,
                ];
            });


            $response = [
                "data" => $allOrdersFormat,
                'current_page' => $allOrders->currentPage(),
                'first_page_url' => $allOrders->url(1),
                'from' => $allOrders->firstItem(),
                'last_page' => $allOrders->lastPage(),
                'last_page_url' => $allOrders->url($allOrders->lastPage()),
                'next_page_url' => $allOrders->nextPageUrl(),
                'path' => $allOrders->url($allOrders->currentPage()),
                'per_page' => $allOrders->perPage(),
                'prev_page_url' => $allOrders->previousPageUrl(),
                'to' => $allOrders->lastItem(),
                'total' => $allOrders->total(),
            ];

            return $response;
        } catch (\Exception $e) {
            //throw $th;
        }
    }

    public function productOrders(Request $request)
    {
        try {

            $purchase_order_id = $request->input('purchase_order_id');
            $response = null;
            $purchaseProducts = PurchaseOrderHeader::with([
                'store:id,store,image_store',
                'personalShopper:id,image_user,name,last_name',
                'client:id,image_user,name,last_name',
                // 'purchaseOrderDetails.product.brand',
                'purchaseOrderDetails:id,product_id,price,amount,image,weight,purchase_order_header_id',
                'purchaseOrderDetails.product:id,name_product,image_product,price_from,price_to,brand_id',
                // 'purchaseOrderDetails.product.receptionCenterDetail',
                'purchaseOrderDetails.product.brand:id,name_brand,image_brand',
                'purchaseInvoices:id,purchase_id,file,extension'
            ])->with('purchaseOrderDetails.receptionCenterDetail', function ($query) use ($purchase_order_id) {
                $query->where('purchase_id', $purchase_order_id);
            })->where('id', $purchase_order_id)
                ->first();

            //   return $purchaseProducts;

            if ($purchaseProducts) {

                $products = [];


                $products = $purchaseProducts->purchaseOrderDetails->map(function ($product) {


                    return [
                        'id' => $product->id,
                        'price' => isset($product->price) ? MoneyConvert::USD($product->price) : null,
                        'amount' => $product->amount,
                        'image' => $product->image,
                        'weight' => $product->weight,
                        'product' => [
                            'id' => $product->product->id,
                            'name' => $product->product->name_product,
                            'image' => $product->product->image,
                            'price_from' => isset($product->product->price_from) ? MoneyConvert::USD($product->product->price_from) : null,
                            'price_to' => isset($product->product->price_to) ? MoneyConvert::USD($product->product->price_to) : null,
                            'reception' => isset($product->receptionCenterDetail) ? [
                                'id' => $product->receptionCenterDetail->id,
                                'purchase_id' => $product->receptionCenterDetail->purchase_id,
                                'purchase_product_id' => $product->receptionCenterDetail->purchase_product_id,
                                'optimal_conditions_product' => $product->receptionCenterDetail->optimal_conditions_product == 1 ? true : false,
                                'verified_quantity' => $product->receptionCenterDetail->verified_quantity == 1 ? true : false,
                                'conditions_brand' => $product->receptionCenterDetail->conditions_brand == 1 ? true : false,
                                'invoice_order' => $product->receptionCenterDetail->invoice_order == 1 ? true : false,
                                'status' => $product->receptionCenterDetail->status,
                                'comment' => $product->receptionCenterDetail->comment
                            ] : null,
                        ],
                        'brand' => [
                            'id' => isset($product->product->brand) ?  $product->product->brand->id : null,
                            'name' => isset($product->product->brand) ?  $product->product->brand->name_brand : null,
                            'image' => isset($product->product->brand) ?  $product->product->brand->image : null,
                        ]
                    ];
                });


                $response = [
                    'id' => $purchaseProducts->id,
                    'store' => $purchaseProducts->store,
                    'personal_shopper' => $purchaseProducts->personalShopper,
                    'invoices' => $purchaseProducts->purchaseInvoices,
                    'client' => $purchaseProducts->client,
                    'details' => $products,
                ];
            }



            return $response;
        } catch (\Exception $e) {
            dd($e);
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }

    public function saveReview(ReceptionCenterRequest  $request)
    {
        try {
            $user = Auth::user();
            $data = $request->all();

            if (isset($data['purchase_id'])) {

                //SI LA ORDEN DE COMPRA ESTA EN EL ESTADO (2 COMPETADO, 7 EN ENVÍO, 3 CANCELADA, 4 DEVOLUCIÓN)
                $validateStatusOrder = PurchaseOrderHeader::where('id', $data['purchase_id'])->whereIn('purchase_status_id', [2, 7, 3, 4])->count();
                if ($validateStatusOrder > 0) {
                    return response()->json(['message' => 'No se puede gestionar la orden en el centro de recepción'], 403);
                }
                /*
                //SE VALIDA SI HAY FACTURAS EN LA ORDEN DE COMPRA
                $validateInvoice = PurchaseInvoice::where('purchase_id', $data['purchase_id'])->count();
                if ($validateInvoice == 0) {
                    return response()->json(['message' => 'No se puede gestionar la orden en el centro de recepción, no hay facturas cargadas'], 403);
                }
                */
            }


            // Crear registros en la base de datos
            DB::beginTransaction();


            foreach ($data['products'] as $productData) {
                $status = 'Aceptado';

                if (!$productData['optimal_conditions_product'] || !$productData['verified_quantity'] || !$productData['conditions_brand'] || !$productData['invoice_order']) {
                    $status = 'Incompleto';
                }

                // $productData['purchase_id'] = $data['purchase_id'];
                // $productData['user_id'] = $user->id;
                // $productData['status'] = $status;


                $recordData = [
                    'purchase_id' => $data['purchase_id'],
                    'purchase_product_id' => $productData['purchase_product_id'],
                    'optimal_conditions_product' => $productData['optimal_conditions_product'],
                    'verified_quantity' => $productData['verified_quantity'],
                    'conditions_brand' => $productData['conditions_brand'],
                    'invoice_order' => $productData['invoice_order'],
                    'status' => $status,
                    'comment' => isset($productData['comment']) ? $productData['comment'] : '',
                    'user_id' => $user->id,
                    'updated_at' => now(), // Establecer updated_at con la fecha y hora actual
                ];

                ReceptionCenter::updateOrInsert(
                    [
                        'purchase_id' => $data['purchase_id'],
                        'purchase_product_id' => $productData['purchase_product_id'],
                    ],
                    $recordData
                );
            }


            $updateOrder = PurchaseOrderHeader::where('id', $data['purchase_id']);
            $updateOrderStatus = $updateOrder->select('purchase_status_id', 'id', 'client_id', 'personal_shopper_id', 'shipment_price')->first();


            //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
            PurchaseOrderHeaderLog::create([
                "purchase_order_id" => $data['purchase_id'],
                "previous_status_id" => $updateOrderStatus->purchase_status_id,
                "description" => "Actualizado desde la pasarela de pago",
                "status_id" => 6
            ]);

            $updateOrder->update([
                'purchase_status_id' => 6
            ]);


            //SE VALIDAN LOS ESTADOS DE LOS PRODUCTOS EN EL CENTRO DE RECEPCIÓN Y SI ALGUNO NO ES 'ACEPTADO' SE RETORNA LA EXCEPCIÓN
            $validarEstadosProductos = ReceptionCenter::where('purchase_id', $data['purchase_id'])->whereNotIn('status', ['Aceptado'])->count();
            if ($validarEstadosProductos == 0) {
                // SI TODOS LOS PRODUCTOS ESTAN EN ESTADO ACEPTADO SE HABILITA LA COMISIÓN PARA EL SHOPPER
                $comissionShopper = Commission::where('purchase_order_id', $data['purchase_id']);
                $comissionShopper->update([
                    'reception_center_ok' => 1
                ]);
            } else {
                $comissionShopper = Commission::where('purchase_order_id', $data['purchase_id']);
                $comissionShopper->update([
                    'reception_center_ok' => 0
                ]);
            }


            DB::commit();

            return response()->json(['message' => 'Información almacenada correctamente'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json(['error' => 'Error al almacenar la información'], 500);
        }
    }
}
