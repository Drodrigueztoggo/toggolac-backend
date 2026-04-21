<?php

namespace App\Http\Controllers;

use App\Exports\PurchasesExport;
use App\Exports\PurchasesSalesOkExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\CreatePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseOrderDetailImage;
use App\Models\PurchaseOrderDetailTax;
use App\Models\PurchaseOrderHeaderLog;
use App\Models\ShoppingCart;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Cknow\Money\Money as MoneyConvert;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{

    public function downloadPurchaseExcelSalesOk()
    {
        try {
            $filter_start_date = request()->query('start_date');
            $filter_end_date = request()->query('end_date');


            $requestFunctionPurchase = new Request([
                'filter_start_date' => Carbon::parse($filter_start_date)->format('Y-m-d'),
                'filter_end_date' => Carbon::parse($filter_end_date)->format('Y-m-d'),
                'purchase_status_id' => '2,5,6,7',
                'no_paginate' => true
            ]);

            $purchaseOrderFormat = $this->getPurchaseOrder($requestFunctionPurchase);

            // dd($purchaseOrderFormat);
            // return $purchaseOrderFormat;

            return Excel::download(new PurchasesSalesOkExport($purchaseOrderFormat), 'Sales.xlsx'); // Nombre del archivo Excel

        } catch (Exception $e) {
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function downloadPurchaseExcel()
    {
        try {
            $filter_start_date = request()->query('start_date');
            $filter_end_date = request()->query('end_date');


            $requestFunctionPurchase = new Request([
                'filter_start_date' => Carbon::parse($filter_start_date)->format('Y-m-d'),
                'filter_end_date' => Carbon::parse($filter_end_date)->format('Y-m-d'),
                'no_paginate' => true
            ]);

            $purchaseOrderFormat = $this->getPurchaseOrder($requestFunctionPurchase);


            // return $purchaseOrderFormat;

            return Excel::download(new PurchasesExport($purchaseOrderFormat), 'Purchases.xlsx'); // Nombre del archivo Excel

        } catch (Exception $e) {
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getPurchaseOrder(Request $request)
    {
        try {
            $translate = new GoogleTranslateController();
            $TGGlanguage = $request->TGGlanguage;
            $currencyFunctions = new CurrencyController();
            $currency = $request->currency;

            $purchaseOrder = PurchaseOrderHeader::with('client')->withoutTrashed()
                ->with(
                    'evaluation',
                    'mallInfo.city',
                    'mallInfo.countryInfo',
                    'mallInfo.state',
                    'purchaseStatus',
                    'shipment.shipmentStatus',
                    'purchaseOrderDetails.store',
                    'purchaseOrderDetails.images',
                    'purchaseOrderDetails.product.categoriesRelation',
                    'purchaseOrderDetails.product.categoriesProduct',
                    'purchaseOrderDetails.product.brand',
                    'purchaseOrderDetails.purchaseOrderDetailTax',
                    'personalShopper'
                )
                ->select(
                    'purchase_order_headers.order_token as order_token',
                    'purchase_order_headers.id as id',
                    'purchase_order_headers.client_id',
                    DB::raw('CONCAT(
                        IF(us.name IS NOT NULL, CONCAT(us.name, " "), ""),
                        IF(us.last_name IS NOT NULL, CONCAT(us.last_name, " "), "")
                    ) as user'),
                    'purchase_order_headers.start_date as start_date',
                    'ml.name_mall as mall',
                    'purchase_order_headers.shipment_status as shipment_status',
                    'purchase_order_headers.purchase_status_id as purchase_status_id',
                    'purchase_order_headers.personal_shopper_id as personal_shopper',
                    'cd.name as destination_city',
                    'st.name as destination_state',
                    'cnto.name as destination_country',
                    'purchase_order_headers.mall_id',
                    'purchase_order_headers.estimated_date as estimated_date',
                    'purchase_order_headers.guide_number as guide_number',
                    'purchase_order_headers.conveyor_id as conveyor_id',
                    'purchase_order_headers.personal_shopper_id',
                    'purchase_order_headers.destination_address AS destination_address',
                    'purchase_order_headers.created_at AS created_at',
                    'purchase_order_headers.shipment_price AS shipment_price',
                    'purchase_order_headers.destination_city_id',
                    'purchase_order_headers.destination_state_id',
                    'purchase_order_headers.destination_country_id',
                    'ca.name as carriers',
                )
                ->leftJoin('malls as ml', 'ml.id', 'purchase_order_headers.mall_id')
                ->leftJoin('carriers as ca', 'ca.id', 'purchase_order_headers.conveyor_id')
                ->leftJoin('users as us', 'us.id', 'purchase_order_headers.client_id')
                // ->join('store_malls as sm', 'sm.id', 'purchase_order_headers.store_id')
                ->leftJoin('cities as cd', 'cd.id', 'purchase_order_headers.destination_city_id')
                ->leftJoin('states as st', 'st.id', 'purchase_order_headers.destination_state_id')
                ->leftJoin('countries as cnto', 'cnto.id', 'purchase_order_headers.destination_country_id');

            $filter_mall_id = $request->query('mall_id');
            $filter_category_id = $request->query('category_id');
            $filter_brand_id = $request->query('brand_id');
            $filter_product_id = $request->query('product_id');
            $filter_date = $request->query('date');
            $per_gage = $request->query('per_page');

            $guide_number = $request->query('guide_number');
            $shipment_status = $request->query('shipment_status');
            $purchase_status_id = $request->query('purchase_status_id');
            $conveyor_id = $request->query('conveyor_id');
            $personal_shopper_id = $request->query('personal_shopper_id');
            $client_id = $request->query('client_id');
            $id_orden = $request->query('id');
            $no_paginate = $request->query('no_paginate');

            //para el exportable
            $filter_start_date = $request->query('filter_start_date');
            $filter_end_date = $request->query('filter_end_date');

            if (isset($filter_start_date) && isset($filter_end_date)) {
                $purchaseOrder->whereDate('purchase_order_headers.created_at', '>=', $filter_start_date)
                    ->whereDate('purchase_order_headers.created_at', '<=', $filter_end_date);
            }
            if (isset($filter_mall_id)) {
                $purchaseOrder->where('purchase_order_headers.mall_id', $filter_mall_id);
            }
            if (isset($filter_category_id)) {
                $purchaseOrder->whereHas('purchaseOrderDetails.product.categoriesProduct', function ($query) use ($filter_category_id) {
                    $query->where('categories_products.category_id', $filter_category_id);
                });
            }
            if (isset($filter_brand_id)) {
                $purchaseOrder->whereHas('purchaseOrderDetails.product', function ($query) use ($filter_brand_id) {
                    $query->where('products.brand_id', $filter_brand_id);
                });
            }
            if (isset($filter_product_id)) {
                $purchaseOrder->whereHas('purchaseOrderDetails', function ($query) use ($filter_product_id) {
                    $query->where('purchase_order_details.product_id', $filter_product_id);
                });
            }
            if (isset($filter_date)) {
                $purchaseOrder->whereDate('purchase_order_headers.created_at', Carbon::parse($filter_date)->format('Y-m-d'));
            }
            if (isset($guide_number)) {
                $purchaseOrder->where('purchase_order_headers.guide_number', 'like', '%' . $guide_number . '%');
            }
            if (isset($shipment_status)) {
                // $purchaseOrder->where('shipment_status', 'like', '%' . $shipment_status . '%');
                $purchaseOrder->where('purchase_order_headers.shipment_status_id', $shipment_status);
            }
            if (isset($purchase_status_id)) {
                // $purchaseOrder->where('purchase_status', 'like', '%' . $purchase_status . '%');

                $statusIds = explode(',', $purchase_status_id);

                // return $statusIds;

                $purchaseOrder->whereIn('purchase_order_headers.purchase_status_id', $statusIds);
            }
            if (isset($conveyor_id)) {
                $purchaseOrder->where('purchase_order_headers.conveyor_id', 'like', '%' . $conveyor_id . '%');
            }
            if (isset($personal_shopper_id)) {
                $purchaseOrder->where('purchase_order_headers.personal_shopper_id', $personal_shopper_id);
            }
            if (isset($client_id)) {
                $purchaseOrder->where('purchase_order_headers.client_id', $client_id);
            }
            if (isset($id_orden)) {
                $purchaseOrder->where('purchase_order_headers.id', $id_orden);
            }
            if ($no_paginate) {
                //NO SE REQUIERE PAGINACION
                $purchaseOrder = $purchaseOrder->orderBy('created_at', 'desc')->get();
            } else {
                $purchaseOrder = $purchaseOrder->orderBy('created_at', 'desc')->paginate($per_gage);
            }

            $purchaseOrderFormat = $purchaseOrder->map(function ($order) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {
                // MoneyConvert::USD($product->price)
                $detailsProducts = collect($order['purchaseOrderDetails'])->map(function ($detailsProduct) use ($TGGlanguage, $translate, $currencyFunctions, $currency) {
                    $taxes = json_decode($detailsProduct->purchaseOrderDetailTax->taxes);
                    // MoneyConvert::USD($product->price)
                    if (isset($detailsProduct->product->categoriesRelation)) {
                        $categories = collect($detailsProduct->product->categoriesRelation)
                            ->map(function ($category) use ($TGGlanguage, $translate) {
                                return [
                                    'id' => $category->id,
                                    "name" => $TGGlanguage != 'es' ? $translate->translateText($category->name_category, $TGGlanguage) : $category->name_category,
                                    "description_category" => $TGGlanguage != 'es' ? $translate->translateText($category->description_category, $TGGlanguage) : $category->description_category,
                                    'image' => $category->image,
                                ];
                            });
                    } else {
                        $categories = null;
                    }
                    return [
                        "id" => $detailsProduct->id,
                        "product_id" => $detailsProduct->product_id,
                        "price" => MoneyConvert::USD($detailsProduct->price),
                        "price_format" => (int)$detailsProduct->price,
                        "price_origin" => round($detailsProduct->price, 2, PHP_ROUND_HALF_UP),
                        "amount" => $detailsProduct->amount,
                        "comment" => $detailsProduct->comment,
                        "weight" => $detailsProduct->weight,
                        "images" => $detailsProduct->images,
                        "store" => isset($detailsProduct->store) ?
                            [
                                "id" => $detailsProduct->store->id,
                                "name" => $TGGlanguage != 'es' ? $translate->translateText($detailsProduct->store->name, $TGGlanguage) : $detailsProduct->store->name,
                                "image_store" => $detailsProduct->store->image_store,
                                "mall_id" => $detailsProduct->store->mall_id,
                                "image" => $detailsProduct->store->image,
                            ]
                            : null,
                            "product" => isset($detailsProduct->product) ? [
                            "id" => $detailsProduct->product->id,
                            "name" => $TGGlanguage != 'es' ? $translate->translateText($detailsProduct->product->name, $TGGlanguage) : $detailsProduct->product->name,
                            "price_from" => (int)$detailsProduct->product->price_from,
                            "price_to" => (int)$detailsProduct->product->price_to,
                            "image" => $detailsProduct->product->image,
                            "categories" => $categories,
                            "brand" => isset($detailsProduct->product->brand) ? [
                                "id" => $detailsProduct->product->brand->id,
                                "name_brand" => $TGGlanguage != 'es' ? $translate->translateText($detailsProduct->product->brand->name_brand, $TGGlanguage) : $detailsProduct->product->brand->name_brand,
                                "image" => $detailsProduct->product->brand->image,
                            ] : null,
                        ] : null,
                        "taxes" => $taxes
                    ];
                });

                $total = $detailsProducts->sum(function ($product) {
                    return $product['amount'] * $product['price_origin'];
                });
                $cost = [
                    [
                        'code' => "PRD",
                        'name' => "TOTAL PRODUCTOS",
                        'tach' => false,
                        'amount' => $total
                    ]
                ];
                $sum = 0;
                foreach ($detailsProducts as $k => $product) {
                    foreach ($product['taxes']->taxes as $j => $tax) {
                        if(isset($tax->code)){
                            $sum = $sum + round($tax->amount, 2, PHP_ROUND_HALF_UP);
                            if ($k === 0) {
                                $cost[] = [
                                    'code' => $tax->code,
                                    'name' => $tax->name,
                                    'tach' => $tax->tach,
                                    'amount' => round($tax->amount, 2, PHP_ROUND_HALF_UP)
                                ];
                            } else {
                                foreach ($cost as $h => $impuesto) {
                                    if ($tax->code === $impuesto['code']) {
                                        $cost[$h]['amount'] = $impuesto['amount'] + round($tax->amount, 2, PHP_ROUND_HALF_UP);
                                        if ($tax->code === "COST") {
                                            if ($impuesto['tach'] && !$tax->tach) {
                                                $cost[$h]['tach'] = false;
                                            } else {
                                                $cost[$h]['tach'] = $tax->tach;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $bond = $this->getBond($order->id, $currencyFunctions);
                $subTotal = $sum + $total;
                $discount = 0;
                $discount_approximate = 0;
                if ($bond !== null){
                    if ($bond["mini_shopping"] < $subTotal){
                        $discount = $bond["amount"];
                        $discount_approximate = $bond["approximate"];
                        array_push($cost, $bond);
                    }
                }
                foreach ($cost as $k => $value) {
                    if ($value["code"] === "SRV") {
                        $cost[$k]["amount"] = $value["amount"];
                    }
                }
                $taxes = [
                    "taxes" => $cost,
                    "total" => $subTotal - $discount,
                    "approximate" => ($currencyFunctions->convertAmount('USD', 'COP', $subTotal)) - $discount_approximate
                ];
                return [
                    "total_payment" => [
                        "amount" => $total,
                        "formatted" => $total,
                        // "amount" => ($total + $order->shipment_price),
                        // "formatted" => $currencyFunctions->convertAmount('USD', "USD", ($total + $order->shipment_price) ? ($total + $order->shipment_price) :  0),
                    ],
                    // "total_product" => MoneyConvert::USD($total),
                    "total_product" => [
                        "amount" => $total,
                        "formatted" => $total,
                    ],
                    "shipment_price" => [
                        "amount" => $order->shipment_price,
                        "formatted" => $order->shipment_price ? $order->shipment_price : 0,
                    ],
                    "order_token" => $order->order_token,
                    "user" => $order->user,
                    "client" => $order->client,
                    // "start_date" => $order->start_date,
                    "start_date" => Carbon::parse($order->created_at)->format('Y-m-d'),
                    "created_at" => $order->created_at,
                    "evaluation" => $order->evaluation,
                    "origin" => isset($order->mallInfo) ? [
                        'city' => $order->mallInfo->city ? ($order->mallInfo->city) : null,
                        'country_info' => isset($order->mallInfo->countryInfo) ? $order->mallInfo->countryInfo : null,
                        'state' => isset($order->mallInfo->state) ? $order->mallInfo->state : null,
                    ] : null,
                    "mall" => $order->mall,
                    // "shipment_status" => $order->shipment_status,
                    "id" => $order->id,
                    "shipment_status" => isset($order->shipment->shipmentStatus) ?
                        [
                            "id" => $order->shipment->shipmentStatus->id,
                            "name" => $TGGlanguage != 'es' ? $translate->translateText($order->shipment->shipmentStatus->name, $TGGlanguage) : $order->shipment->shipmentStatus->name,
                            "created_at" => isset($order->shipment->date) ? $order->shipment->date : null
                        ]
                        : [
                            "name" => "En espera para envío"
                        ],
                    "purchase_status" => $TGGlanguage != 'es' ? $translate->translateText($order->purchaseStatus->name, $TGGlanguage) : $order->purchaseStatus->name,
                    "purchase_status_id" => $order->purchase_status_id,
                    "personal_shopper" => $order->personal_shopper,
                    "personal_shopper_info" => isset($order->personalShopper) ? [
                        "id" => $order->personalShopper->id,
                        "name" => $order->personalShopper->name,
                        "last_name" => $order->personalShopper->last_name,
                    ] : null,
                    "destination_city" => $order->destination_city,
                    "destination_state" => $order->destination_state,
                    "destination_country" => $order->destination_country,
                    "destination_city_id" => $order->destination_city_id,
                    "destination_state_id" => $order->destination_state_id,
                    "destination_country_id" => $order->destination_country_id,
                    "destination_address" => $order->destination_address,
                    "estimated_date" => $order->estimated_date,
                    "guide_number" => isset($order->shipment->tracking_number) ? $order->shipment->tracking_number : null,
                    "conveyor_id" => $order->conveyor_id,
                    "carriers" => $TGGlanguage != 'es' ? $translate->translateText($order->carriers, $TGGlanguage) : $order->carriers,
                    "purchase_order_details" => $detailsProducts,
                    "taxes" => $taxes
                ];
            });

            if ($no_paginate) {
                //NO SE REQUIERE PAGINACION
                $response = $purchaseOrderFormat;
            } else {
                $response = [
                    "data" => $purchaseOrderFormat,
                    'current_page' => $purchaseOrder->currentPage(),
                    'first_page_url' => $purchaseOrder->url(1),
                    'from' => $purchaseOrder->firstItem(),
                    'last_page' => $purchaseOrder->lastPage(),
                    'last_page_url' => $purchaseOrder->url($purchaseOrder->lastPage()),
                    'next_page_url' => $purchaseOrder->nextPageUrl(),
                    'path' => $purchaseOrder->url($purchaseOrder->currentPage()),
                    'per_page' => $purchaseOrder->perPage(),
                    'prev_page_url' => $purchaseOrder->previousPageUrl(),
                    'to' => $purchaseOrder->lastItem(),
                    'total' => $purchaseOrder->total(),
                ];
            }

            return $response;
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function showPurchaseOrder(Request $request, $id)
    {
        try {

            $requestFunctionPurchase = new Request([
                'id' => $id,
                'no_paginate' => true,
                'currency' => $request->currency,
                'TGGlanguage' => $request->TGGlanguage,
            ]);

            $purchaseOrderFormat = $this->getPurchaseOrder($requestFunctionPurchase);

            if (!isset($purchaseOrderFormat) && count($purchaseOrderFormat) != 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }


            return count($purchaseOrderFormat) == 1 ? $purchaseOrderFormat[0] : null;
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addPurchaseOrder(CreatePurchaseOrderRequest $request)
    {
        try {

            DB::beginTransaction();

            $orderToken = Str::random(8);

            $request = $request->all();
            $createUserId = auth()->user()->id;

            //TODAS LAS ORDENES QUE ESTEN EN ESTADO PENDIENTES PARA ESE CLIENTE SE PONEN COMO FALLIDAS, PARA TENER SOLO 1 ORDEN A LA VEZ
            $deleteOrders = PurchaseOrderHeader::where('client_id', $request['client_id'])->where('purchase_status_id', 1);
            $deleteOrdersIds = $deleteOrders->pluck('id')->toArray();

            if (count($deleteOrdersIds) > 0) {

                foreach ($deleteOrdersIds as $key => $order) {

                    //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
                    PurchaseOrderHeaderLog::create([
                        "purchase_order_id" => $order,
                        "status_id" => 3,
                        "description" => "Eliminada para crear una nueva orden de compra",
                        "user_id" => $createUserId
                    ]);
                }
            }


            $deleteOrders->update([
                "purchase_status_id" => 3
            ]);

            // $request['order_token'] = $orderToken;

            $header = PurchaseOrderHeader::create($request);

            // Pre-compute total order price for the free-shipping threshold check
            $totalOrderPrice = collect($request['details'])
                ->sum(fn($d) => ((float)($d['price'] ?? 0)) * ((int)($d['amount'] ?? 1)));
            $destinationPostalCode = $request['destination_postal_code'] ?? null;

            //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
            PurchaseOrderHeaderLog::create([
                "purchase_order_id" => $header->id,
                "status_id" => 1,
                "description" => "Nueva orden de compra",
                "user_id" => $createUserId
            ]);

            foreach ($request['details'] as $key => $detail) {

                $detail['price_origin'] = $detail['price'];

                $insertDetail = PurchaseOrderDetail::create([
                    'purchase_order_header_id' => $header->id,
                    ...$detail
                ]);



                if (isset($request['order_cart']) && $request['order_cart']) {
                    //ES UNA ORDEN DE COMPRA GENERADA A PARTIR DE UN CARRITO DE UN USUARIO

                    $client_id = $request['client_id'];

                    $updateCart = ShoppingCart::where('user_id', $client_id)
                        ->whereNull('deleted_at')
                        ->where('product_id', $detail['product_id'])->update([
                                'is_purchase_order' => true
                            ]);
                }



                if (isset($detail['image']) && count($detail['image']) > 0) {

                    foreach ($detail['image'] as $key => $imagen) {
                        $imagePath = $this->storeImage($imagen);

                        $insertImage = new PurchaseOrderDetailImage();
                        $insertImage->purchase_order_detail_id = $insertDetail->id;
                        $insertImage->image_purchase = $imagePath;
                        $insertImage->save();
                    }
                }



                try {


                    $requestFunctionTaxes = new Request([
                        'category_id'             => $detail['category_id'],
                        'destination_country_id'  => $request['destination_country_id'],
                        'producto_precio'         => $detail['price'],
                        'weight'                  => $detail['weight'],
                        'manual_tax'              => isset($detail['manual_tax']) ? $detail['manual_tax'] : null,
                        'total_order_price'       => $totalOrderPrice,
                        'destination_postal_code' => $destinationPostalCode,
                    ]);

                    $taxesFunction = new TaxController();
                    $taxes = $taxesFunction->calculateTaxes($requestFunctionTaxes);

                    // return $taxes;

                    $updatePrice = PurchaseOrderDetail::where('id', $insertDetail->id)->update([
                        "price" => $taxes['total']
                    ]);


                    $insertTax = PurchaseOrderDetailTax::create([
                        "purchase_order_detail_id" => $insertDetail->id,
                        "taxes" => json_encode($taxes)
                    ]);
                } catch (\Exception $e) {
                    //throw $th;
                }
            }

            DB::commit();

            return response()->json($request, 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePurchaseOrder(UpdatePurchaseOrderRequest $request)
    {
        try {
            $request = $request->all();
            $purchaseOrder = PurchaseOrderHeader::findOrFail($request['id']);

            if (is_null($purchaseOrder)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró la orden de compra.'
                ], 404);
            }

            $purchaseOrder->update($request);

            return response()->json($request, 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deletePurchaseOrder($id)
    {
        try {
            DB::beginTransaction();
            $createUserId = auth()->user()->id;

            $purchaseOrder = PurchaseOrderHeader::findOrFail($id);

            if (!isset($purchaseOrder)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }



            //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
            PurchaseOrderHeaderLog::create([
                "purchase_order_id" => $id,
                "previous_status_id" => $purchaseOrder->purchase_status_id,
                "status_id" => 3,
                "description" => "Orden de compra eliminada",
                "user_id" => $createUserId
            ]);

            $purchaseOrder->purchase_status_id = 3;
            $purchaseOrder->save();

            // PurchaseOrderDetail::where('purchase_order_header_id', $id)->delete();
            DB::commit();
            return [
                'status' => 'success',
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function storeImage($image)
    {
        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('uploads/purchase_order/' . now()->format('Y-m-d'), $imageName, 'public');
        return $imagePath;
    }

    public function addPurchaseOrderFromShopCar(Request $request)
    {
        try {
            DB::beginTransaction();
            $currency = $request['currency'];
            $request = $request->all();
            $createUserId = auth()->user()->id;
            //TODAS LAS ORDENES QUE ESTEN EN ESTADO PENDIENTES PARA ESE CLIENTE SE PONEN COMO FALLIDAS, PARA TENER SOLO 1 ORDEN A LA VEZ
            $query = PurchaseOrderHeader::with('bondPurchaseOrder')->where('client_id', $request['client_id'])->where('purchase_status_id', 1)->first();
            $deleteOrders = PurchaseOrderHeader::where('client_id', $request['client_id'])->where('purchase_status_id', 1);
            $deleteOrdersIds = $deleteOrders->pluck('id')->toArray();
            if (count($deleteOrdersIds) > 0) {
                foreach ($deleteOrdersIds as $key => $order) {
                    //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
                    PurchaseOrderHeaderLog::create([
                        "purchase_order_id" => $order,
                        "status_id" => 3,
                        "description" => "Eliminada para crear una nueva orden de compra",
                        "user_id" => $createUserId
                    ]);

                }
            }
            $deleteOrders->update([
                "purchase_status_id" => 3
            ]);
            if (count($request['details']) > 0) {
                // Pre-compute total order price for the free-shipping threshold check
                $totalOrderPrice = collect($request['details'])
                    ->sum(fn($d) => ((float)($d['price'] ?? 0)) * ((int)($d['amount'] ?? 1)));
                $destinationPostalCode = $request['destination_postal_code'] ?? null;

                $header = PurchaseOrderHeader::create($request);
                if(!is_null($query)){
                    if (count($query->bondPurchaseOrder) > 0 ){
                        $header->bondPurchaseOrder()->attach($query->bondPurchaseOrder[0]->id);
                        $query->bondPurchaseOrder()->detach();
                    }
                }


                //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
                PurchaseOrderHeaderLog::create([
                    "purchase_order_id" => $header->id,
                    "status_id" => 1,
                    "description" => "Nueva orden de compra",
                    "user_id" => $createUserId
                ]);
                foreach ($request['details'] as $key => $detail) {

                    $detail['price_origin'] = $detail['price'];

                    $insertDetail = PurchaseOrderDetail::create([
                        'purchase_order_header_id' => $header->id,
                        ...$detail
                    ]);

                    if (isset($request['order_cart']) && $request['order_cart']) {
                        //ES UNA ORDEN DE COMPRA GENERADA A PARTIR DE UN CARRITO DE UN USUARIO

                        $client_id = $request['client_id'];

                        $updateCart = ShoppingCart::where('user_id', $client_id)
                            ->whereNull('deleted_at')
                            ->where('product_id', $detail['product_id'])->update([
                                    'is_purchase_order' => true
                                ]);
                    }

                    if (isset($detail['image'])) {
                        $insertImage = new PurchaseOrderDetailImage();
                        $insertImage->purchase_order_detail_id = $insertDetail->id;
                        $insertImage->image_purchase = $detail['image'];
                        $insertImage->save();
                    }

                    try {
                        $requestFunctionTaxes = new Request([
                            'category_id'              => $detail['category_id'],
                            'destination_country_id'   => $request['destination_country_id'],
                            'producto_precio'          => $detail['price'] * $detail['amount'],
                            'weight'                   => $detail['weight'] * $detail['amount'],
                            'manual_tax'               => isset($detail['manual_tax']) ? $detail['manual_tax'] : null,
                            'total_order_price'        => $totalOrderPrice,
                            'destination_postal_code'  => $destinationPostalCode,
                        ]);

                        $taxesFunction = new TaxController();
                        $taxes = $taxesFunction->calculateTaxes($requestFunctionTaxes);
                        PurchaseOrderDetailTax::create([
                            "purchase_order_detail_id" => $insertDetail->id,
                            "taxes" => json_encode($taxes)
                        ]);
                    } catch (\Exception $e) {
                        //throw $th;
                    }
                }
            } else{
                if(!is_null($query)){
                    $query->bondPurchaseOrder()->detach();
                }
            }
            DB::commit();
            return response()->json($request, 201);
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getBond(int $purchaseOrderId, $currencyFunctions ){
        try{
            $purchaseBond = PurchaseOrderHeader::with('bondPurchaseOrder')->findOrFail($purchaseOrderId);
            if(count($purchaseBond->bondPurchaseOrder) > 0){
                return [
                    'code' => "BOND",
                    'name' => "CUPÓN",
                    'tach' => false,
                    "mini_shopping" => $purchaseBond->bondPurchaseOrder[0]->minimun_amount,
                    "amount" => $purchaseBond->bondPurchaseOrder[0]->value_bond,
                    "approximate" => $currencyFunctions->convertAmount('USD', 'COP', $purchaseBond->bondPurchaseOrder[0]->value_bond)
                ];
            }
            return null;
        } catch(Exception $e){
            dd($e);
        }
    }
}
