<?php

namespace App\Http\Controllers;

use App\Exports\ShipmentExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShipmentRequest;
use App\Mail\ShipmentPurchaseOrderMail;
use App\Models\CarrierShippingRate;
use App\Models\City;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseOrderHeaderLog;
use App\Models\ReceptionCenter;
use App\Models\Shipment;
use App\Models\ShipmentLogStatus;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Cknow\Money\Money as MoneyConvert;
use Exception;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ShipmentController extends Controller
{


    public function downloadShipmentPdfThank(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
            ]);

            //showInfoShipment

            $requestFunctionShowInfoShipment = new Request([
                'id' => $request->id
            ]);

            $infoShipment = $this->showInfoShipment($requestFunctionShowInfoShipment);

            if (isset($infoShipment)) {
                // Obten los datos que quieres incluir en el PDF
                // return $infoShipment['data'];
                $data = [
                    'originaddress' => isset($infoShipment['data']->origin_address) ? $infoShipment['data']->origin_address : null,
                    'origincity' => isset($infoShipment['data']->originCity->name) ? $infoShipment['data']->originCity->name : null,
                    'originstate' => isset($infoShipment['data']->originState->name) ? $infoShipment['data']->originState->name : null,
                    'originzipcode' => $infoShipment['data']->origin_postal_code,
                    'origincountry' => isset($infoShipment['data']->originCountry->name) ? $infoShipment['data']->originCountry->name : null,
                    'origindate' => $infoShipment['data']->date,
                    'originweight' => $infoShipment['data']->pounds_weight,
                    'destinationaddress' => $infoShipment['data']->destination_address,
                    'destinationcity' => isset($infoShipment['data']->destinationCity->name) ? $infoShipment['data']->destinationCity->name : null,
                    'destinationstate' => isset($infoShipment['data']->destinationState->name) ? $infoShipment['data']->destinationState->name : null,
                    'destinationzipcode' => $infoShipment['data']->destination_postal_code,
                    'destinationcountry' => isset($infoShipment['data']->destinationCountry->name) ? $infoShipment['data']->destinationCountry->name : null,
                    'destinationname' => $infoShipment['data']->customer_name_lastname
                ];

                $pdf = PDF::loadView('Export.pdf.shipping', $data);

                // Establece la orientación del PDF a horizontal
                $pdf->setPaper('A4', 'landscape');

                // Descarga el PDF con el nombre 'example.pdf'
                return $pdf->download('shipment-' . $request->id . '.pdf');
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se ha encontrado información del envío',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    public function downloadShipmentExcel()
    {
        try {
            $filter_start_date = request()->query('start_date');
            $filter_end_date = request()->query('end_date');


            $requestFunctionShipment = new Request([
                'filter_start_date' => Carbon::parse($filter_start_date)->format('Y-m-d'),
                'filter_end_date' => Carbon::parse($filter_end_date)->format('Y-m-d'),
                'no_paginate' => true
            ]);

            $shipmentFormat = $this->getShipment($requestFunctionShipment);

            // return $shipmentFormat;

            return Excel::download(new ShipmentExport($shipmentFormat), 'Shipment.xlsx'); // Nombre del archivo Excel

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    public function getShipment(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 20);


            // shipment_id
            // name_customer
            // origin_city
            // destination_city
            // date
            // tracking_number

            $filter_purchase_order_id = $request->query('purchase_order_id');
            $filter_shipment_id = $request->query('shipment_id');
            $filter_name_customer = $request->query('name_customer');
            $filter_origin_city = $request->query('origin_city');
            $filter_origin_city_name = $request->query('origin_city_name');
            $filter_destination_city = $request->query('destination_city');
            $filter_destination_city_name = $request->query('destination_city_name');
            $filter_date = $request->query('date');
            $filter_tracking_number = $request->query('tracking_number');

            $no_paginate = $request->query('no_paginate');




            $query = Shipment::with(
                'orderDetail.client',
                'originCountry',
                'originState',
                'originCity',
                'destinationCountry',
                'destinationState',
                'destinationCity',
                'createUser',
                'shipmentStatus',
                'shipmentLogStatuses.user',
                'shipmentLogStatuses.status',
            )->select(
                'id',
                'purchase_order_id',
                'carrier_id',
                'origin_address',
                'destination_address',
                'shipment_status_id',
                'customer_name_lastname',
                'origin_country_id',
                'origin_state_id',
                'origin_city_id',
                'destination_country_id',
                'destination_state_id',
                'destination_city_id',
                'tracking_number',
                'date',
                'origin_postal_code',
                'destination_postal_code',
                'pounds_weight',
                'total_shipping_cost',
                'current_cost',
                'create_user_id',
                'created_at'
            );

            if (isset($filter_shipment_id)) {
                $query->where('id', $filter_shipment_id);
            }
            if (isset($filter_purchase_order_id)) {
                $query->where('purchase_order_id', $filter_purchase_order_id);
            }
            if (isset($filter_name_customer)) {
                $query->where('customer_name_lastname', 'LIKE', '%' . $filter_name_customer . '%');
            }
            if (isset($filter_origin_city)) {
                $query->where('origin_city_id', $filter_origin_city);
            }
            if (isset($filter_destination_city)) {
                $query->where('destination_city_id', $filter_destination_city);
            }
            if (isset($filter_date)) {

                $date =  Carbon::parse($filter_date)->format('Y-m-d');
                $query->where('date', $date);
            }
            if (isset($filter_tracking_number)) {
                $query->where('tracking_number', $filter_tracking_number);
            }
            if (isset($filter_origin_city_name)) {
                $query->whereHas('originCity', function ($query) use ($filter_origin_city_name) {
                    $query->where('name', 'LIKE', '%' . $filter_origin_city_name . '%');
                });
            }
            if (isset($filter_destination_city_name)) {
                $query->whereHas('destinationCity', function ($query) use ($filter_destination_city_name) {
                    $query->where('name', 'LIKE', '%' . $filter_destination_city_name . '%');
                });
            }

            $filter_start_date = $request->query('filter_start_date');
            $filter_end_date = $request->query('filter_end_date');

            if (isset($filter_start_date) && isset($filter_end_date)) {
                $query->whereDate('date', '>=', $filter_start_date)
                    ->whereDate('date', '<=', $filter_end_date);
            }

            if ($no_paginate) {
                //NO SE REQUIERE PAGINACION
                $shipment = $query->orderBy('created_at', 'desc')->get();
            } else {
                $shipment = $query->orderBy('created_at', 'desc')->paginate($perPage);
            }



            // Formatear los resultados antes de enviar la respuesta JSON
            $formattedShipment = $shipment->map(function ($shipmentData) {
                return [
                    'id' => $shipmentData->id,
                    'purchase_order_id' => $shipmentData->purchase_order_id,
                    'origin_country' => isset($shipmentData->originCountry->name) ? $shipmentData->originCountry : null,
                    'origin_state' => isset($shipmentData->originState->name) ? $shipmentData->originState : null,
                    'origin_city' => isset($shipmentData->originCity->name) ? $shipmentData->originCity : null,
                    'destination_Country' => isset($shipmentData->destinationCountry->name) ? $shipmentData->destinationCountry : null,
                    'destination_state' => isset($shipmentData->destinationState->name) ? $shipmentData->destinationState : null,
                    'destination_city' => isset($shipmentData->destinationCity->name) ? $shipmentData->destinationCity : null,
                    'shipment_status' => isset($shipmentData->shipmentStatus->name) ? $shipmentData->shipmentStatus : null,
                    'shipment_status_id' => isset($shipmentData->shipment_status_id) ? $shipmentData->shipment_status_id : null,
                    'cliente' => isset($shipmentData->orderDetail) ? $shipmentData->orderDetail->client : null,
                    'date' => $shipmentData->date,
                    'origin_address' => $shipmentData->origin_address,
                    'destination_address' => $shipmentData->destination_address,
                    'origin_postal_code' => $shipmentData->origin_postal_code,
                    'destination_postal_code' => $shipmentData->destination_postal_code,
                    'guide_number' => $shipmentData->tracking_number,
                    'created_at' => Carbon::parse($shipmentData->created_at)->format('Y-m-d'),
                    'total_shipping_cost' =>  MoneyConvert::USD($shipmentData->total_shipping_cost),
                    'label_url'  => $shipmentData->label_url,
                ];
            })->toArray(); // Convertir la colección a un array;


            if ($no_paginate) {
                //NO SE REQUIERE PAGINACION
                return $formattedShipment;
            } else {
                // Agregar datos de paginación
                $paginationData = [
                    'current_page' => $shipment->currentPage(),
                    'first_page_url' => $shipment->url(1),
                    'from' => $shipment->firstItem(),
                    'last_page' => $shipment->lastPage(),
                    'last_page_url' => $shipment->url($shipment->lastPage()),
                    'next_page_url' => $shipment->nextPageUrl(),
                    'path' => $shipment->url($shipment->currentPage()),
                    'per_page' => $shipment->perPage(),
                    'prev_page_url' => $shipment->previousPageUrl(),
                    'to' => $shipment->lastItem(),
                    'total' => $shipment->total(),
                ];

                return response()->json([
                    'data' => $formattedShipment,
                    'pagination' => $paginationData, // Agregar datos de paginación
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function showInfoShipment(Request $request)
    {
        try {
            $id = $request->id;

            $query = Shipment::with(
                'orderDetail.client',
                'originCountry',
                'originState',
                'originCity',
                'destinationCountry',
                'destinationState',
                'destinationCity',
                'createUser',
                'shipmentStatus',
                'shipmentLogStatuses.user',
                'shipmentLogStatuses.status',
            )->select(
                'id',
                'purchase_order_id',
                'carrier_id',
                'origin_address',
                'destination_address',
                'shipment_status_id',
                'customer_name_lastname',
                'origin_country_id',
                'origin_state_id',
                'origin_city_id',
                'destination_country_id',
                'destination_state_id',
                'destination_city_id',
                'tracking_number',
                'date',
                'origin_postal_code',
                'destination_postal_code',
                'pounds_weight',
                'package_length',
                'package_width',
                'package_height',
                'total_shipping_cost',
                'shippo_transaction_id',
                'label_url',
                'create_user_id'
            );

            $shipment = $query->find($id);




            return [
                'data' => $shipment,
            ];
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function calculateShippingCost(Request $request)
    {
        try {
            $carrierId = $request->input('carrier_id');
            $destinationCountryId = $request->input('country_id');
            $destinationCityId = $request->input('city_id');
            $weight = $request->input('weight');

            // Verificar si el país de destino es una ciudad principal
            $destinationCity = City::findOrFail($destinationCityId);
            $isMainCity = $destinationCity->is_main;

            // Obtener la tarifa de envío correspondiente
            $shippingRate = CarrierShippingRate::where('carrier_id', $carrierId)
                ->where('country_id', $destinationCountryId)
                ->where('min_weight', '<=', $weight)
                ->where('max_weight', '>=', $weight)
                ->first();

            if (!$shippingRate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró una tarifa de envío para el destino y peso especificados.'
                ], 404);
            }

            // Calcular el costo base del envío
            $baseCost = $shippingRate->price;

            // Como se realiza el cobro, total o precio por libra
            $typePrice = $shippingRate->price_type;

            if ($typePrice === 'per_pound') {
                $baseCost = $baseCost * $weight;
            }

            // Agregar el cargo adicional si no es una ciudad principal
            if (!$isMainCity) {
                $additionalCharge = $shippingRate->additional_charge;
                $baseCost += $additionalCharge;
            }



            return response()->json([
                'status' => 'success',
                'id' => $shippingRate->id,
                'carrier_id' => $carrierId,
                'destination_country_id' => $destinationCountryId,
                'weight' => $weight,
                'is_main_city' => $isMainCity,
                'shipping_cost_format' => MoneyConvert::USD($baseCost),
                'shipping_cost' => $baseCost
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addShipment(ShipmentRequest $request)
    {
        try {
            DB::beginTransaction();

            $validateShipment = Shipment::where('purchase_order_id', $request->purchase_order_id)->count();
            if($validateShipment > 0){
                return response()->json(['message' => 'Ya se ha realizado un envío para esta orden de compra ' . '"'.$request->purchase_order_id.'"'], 403);
            }

            // Obtiene el ID del usuario autenticado
            $createUserId = auth()->user()->id;

            //SI EL ESTADO ACTUAL DE LA ORDEN ES 6 ES DECIR EN CENTRO DE RECEPCIÓN SE PUEDE REALIZAR EL ENVÍO
            $validatEstadoOrden = PurchaseOrderHeader::where('id', $request->purchase_order_id)->where('purchase_status_id', 6)->count();
            if ($validatEstadoOrden == 0) {
                return response()->json(['message' => 'No se puede realizar el envío, la orden de compra no se encuentra en centro de recepción'], 403);
            } else {
                //SE VALIDAN LOS ESTADOS DE LOS PRODUCTOS EN EL CENTRO DE RECEPCIÓN Y SI ALGUNO NO ES 'ACEPTADO' SE RETORNA LA EXCEPCIÓN
                $validarEstadosProductos = ReceptionCenter::where('purchase_id', $request->purchase_order_id)->whereNotIn('status', ['Aceptado'])->count();
                if ($validarEstadosProductos > 0) {
                    return response()->json(['message' => 'Alguno de los productos de la orden de compra no fueron aceptados por el centro de recepción'], 403);
                }
            }
            //SI EL ESTADO ACTUAL DE LA ORDEN ES 6 ES DECIR EN CENTRO DE RECEPCIÓN SE PUEDE REALIZAR EL ENVÍO


            // Crea un nuevo objeto Shipment con los datos validados automáticamente
            $data = $request->validated();
            $data['shipment_status_id'] = 2; // shipment
            $data['create_user_id'] = $createUserId;
            // $data['current_cost'] = json_encode($carrierShippingRate); // Convierte el objeto a JSON

            $shipment = new Shipment($data);

            // Guarda el objeto en la base de datos
            $shipment->save();


            // Crear un registro en ShipmentLogStatus para el estado
            $ShimentLogStatus = new ShipmentLogStatus([
                'shipment_id' => $shipment->id,
                'status_id' => 2, // shipment
                'user_id' => $createUserId // Usar el ID del usuario autenticado
            ]);

            $ShimentLogStatus->save();

            //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
            PurchaseOrderHeaderLog::create([
                "purchase_order_id" => $request->purchase_order_id,
                "previous_status_id" => 6,
                "description" => "La orden se encuentra en proceso de envio",
                "status_id" => 7
            ]);

            PurchaseOrderHeader::where('id', $request->purchase_order_id)->update([
                'purchase_status_id' => 7 //EN PROCESO DE ENVÍO
            ]);

            $this->sendEmailShipmentOrder($request->purchase_order_id);

            DB::commit();

            return response()->json(['message' => 'Envío creado exitosamente'], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error
            // Maneja la excepción y retorna una respuesta JSON de error
            return response()->json(['error' => 'Error al crear el envío: ' . $e->getMessage()], 500);
        }
    }


    public function sendEmailShipmentOrder($order_id)
    {
        try {

            $requestFunctionShipment = new Request([
                'purchase_order_id' => $order_id,
                'no_paginate' => true
            ]);


            $shipmentInfo = $this->getShipment($requestFunctionShipment);


            if ($shipmentInfo && count($shipmentInfo) > 0) {

                // return $shipmentInfo[0];

                $name = (isset($shipmentInfo[0]['cliente']['name']) ? $shipmentInfo[0]['cliente']['name'] : null) . (isset($shipmentInfo[0]['cliente']['last_name']) ? ' ' . $shipmentInfo[0]['cliente']['last_name'] : null);
                $numseguimiento = isset($shipmentInfo[0]['guide_number']) ? $shipmentInfo[0]['guide_number'] : null;
                $fechaenvio = isset($shipmentInfo[0]['created_at']) ? $shipmentInfo[0]['created_at'] : null;
                $ciudaddestino = isset($shipmentInfo[0]['destination_city']['name']) ? $shipmentInfo[0]['destination_city']['name'] : null;
                $direcciondestino = isset($shipmentInfo[0]['destination_address']) ? $shipmentInfo[0]['destination_address'] : null;
                $fechaestimada = isset($shipmentInfo[0]['date']) ? $shipmentInfo[0]['date'] : null;
                $destinationpostalcode = isset($shipmentInfo[0]['destination_postal_code']) ? $shipmentInfo[0]['destination_postal_code'] : null;

                if (isset($shipmentInfo[0]['cliente']['email'])) {
                    Mail::to($shipmentInfo[0]['cliente']['email'])->send(new ShipmentPurchaseOrderMail(
                        $name,
                        $numseguimiento,
                        $fechaenvio,
                        $ciudaddestino,
                        $direcciondestino,
                        $fechaestimada,
                        $destinationpostalcode
                    ));
                }

                // 
            }
        } catch (\Exception $e) {
            //throw $th;
            // dd($e);
        }
    }

    public function updateShipment(Request $request)
    {
        try {
            DB::beginTransaction();

            $id = $request->input('id');

            // Obtén el envío por su ID
            $shipment = Shipment::find($id);

            if (!$shipment) {
                return response()->json(['error' => 'Envío no encontrado'], 404);
            } else {
                if ($shipment->shipment_status_id == 4) {
                    return response()->json(['error' => 'No se puede actualizar el envio, ya se encuentra en estado completado'], 403);
                }
            }

            // Actualiza los campos shipment_status_id y tracking_number si están presentes en la solicitud
            if ($request->has('shipment_status_id')) {
                $shipment->shipment_status_id = $request->input('shipment_status_id');

                // Guarda un registro en ShipmentLogStatus para el nuevo estado
                $shipmentLogStatus = new ShipmentLogStatus([
                    'shipment_id' => $shipment->id,
                    'status_id' => $shipment->shipment_status_id,
                    'user_id' => auth()->user()->id // Usar el ID del usuario autenticado
                ]);

                $shipmentLogStatus->save();


                $shipment_status_id = $request->input('shipment_status_id');
            }

            if ($request->has('tracking_number') && isset($request->tracking_number)) {
                $shipment->tracking_number = $request->input('tracking_number');
            }
            if ($request->has('customer_name_lastname') && isset($request->customer_name_lastname)) {
                $shipment->customer_name_lastname = $request->input('customer_name_lastname');
            }
            if ($request->has('destination_address') && isset($request->destination_address)) {
                $shipment->destination_address = $request->input('destination_address');
            }
            if ($request->has('date') && isset($request->date)) {
                $shipment->date = $request->input('date');
            }

            // Guarda los cambios en el objeto Shipment
            $shipment->save();

            if ($shipment->shipment_status_id  == 4) {
                //SI SE ACTUALIZO A COMPLETADO
                //LA ORDEN DE COMPRA SE PASA A COMPLETADA ES DECIR ESTADO 2

                //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
                PurchaseOrderHeaderLog::create([
                    "purchase_order_id" => $shipment->purchase_order_id,
                    "previous_status_id" => 7,
                    "description" => "La orden se encuentra completada",
                    "status_id" => 2
                ]);

                PurchaseOrderHeader::where('id', $shipment->purchase_order_id)->update([
                    'purchase_status_id' => 2 //COMPLETADA
                ]);
            }




            DB::commit();

            return response()->json(['message' => 'Envío actualizado exitosamente'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar el envío: ' . $e->getMessage()], 500);
        }
    }

    // ── Label generation ──────────────────────────────────────────────────────

    /**
     * Generate a 4×6 shipping label.
     * - US destination  → Shippo API (PDF_4X6, cheapest rate)
     * - Colombia or other → DomPDF custom 4×6 label stored in /storage
     *
     * Optional request body: package_length, package_width, package_height (inches)
     */
    public function generateLabel(Request $request, int $id)
    {
        try {
            $shipment = Shipment::with('destinationCountry', 'destinationState', 'destinationCity')->findOrFail($id);

            // Persist optional package dimensions
            foreach (['package_length', 'package_width', 'package_height'] as $field) {
                if ($request->filled($field)) {
                    $shipment->$field = (float) $request->input($field);
                }
            }
            $shipment->save();

            $countryName = strtolower($shipment->destinationCountry->name ?? '');
            $isUS = str_contains($countryName, 'united states') || str_contains($countryName, 'estados unidos') || $countryName === 'us' || $countryName === 'usa';

            if ($isUS) {
                $result = $this->generateShippoLabel($shipment);
            } else {
                $result = $this->generateColombiaLabel($shipment);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("generateLabel failed for shipment {$id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /** Stream the stored label PDF to the browser (inline, for printing). */
    public function downloadLabel(int $id)
    {
        try {
            $shipment = Shipment::with('destinationCountry', 'destinationState', 'destinationCity')->findOrFail($id);

            // If no label yet, generate on the fly
            if (!$shipment->label_url) {
                $countryName = strtolower($shipment->destinationCountry->name ?? '');
                $isUS = str_contains($countryName, 'united states') || str_contains($countryName, 'estados unidos');
                $result = $isUS ? $this->generateShippoLabel($shipment) : $this->generateColombiaLabel($shipment);
                $shipment->refresh();
            }

            // US labels: redirect to Shippo-hosted PDF
            if ($shipment->shippo_transaction_id && $shipment->label_url) {
                return redirect($shipment->label_url);
            }

            // Colombia labels: stream from local storage
            $path = 'labels/shipment-' . $id . '.pdf';
            if (!Storage::disk('local')->exists($path)) {
                $this->generateColombiaLabel($shipment);
            }

            return response()->streamDownload(function () use ($path) {
                echo Storage::disk('local')->get($path);
            }, 'label-shipment-' . $id . '.pdf', [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="label-shipment-' . $id . '.pdf"',
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function generateShippoLabel(Shipment $shipment): array
    {
        $token  = config('services.shippo.token');
        $client = new Client(['base_uri' => 'https://api.goshippo.com/', 'timeout' => 30]);
        $headers = ['Authorization' => 'ShippoToken ' . $token, 'Content-Type' => 'application/json'];

        // Build addresses
        $addressFrom = [
            'name'    => config('services.shippo.origin_name', 'Toggolac'),
            'street1' => config('services.shippo.origin_street', '7819 NW 104th Ave Apt 6'),
            'city'    => config('services.shippo.origin_city', 'Doral'),
            'state'   => config('services.shippo.origin_state', 'FL'),
            'zip'     => config('services.shippo.origin_zip', '33178'),
            'country' => 'US',
        ];

        $addressTo = [
            'name'    => $shipment->customer_name_lastname,
            'street1' => $shipment->destination_address,
            'city'    => $shipment->destinationCity->name   ?? '',
            'state'   => $shipment->destinationState->name  ?? '',
            'zip'     => $shipment->destination_postal_code ?? '',
            'country' => 'US',
        ];

        $parcel = [
            'length'        => $shipment->package_length  ?? 12,
            'width'         => $shipment->package_width   ?? 10,
            'height'        => $shipment->package_height  ?? 6,
            'distance_unit' => 'in',
            'weight'        => $shipment->pounds_weight   ?? 1,
            'mass_unit'     => 'lb',
        ];

        // Create shipment and get rates
        $shipRes  = $client->post('shipments/', ['headers' => $headers, 'json' => [
            'address_from' => $addressFrom,
            'address_to'   => $addressTo,
            'parcels'      => [$parcel],
            'async'        => false,
        ]]);
        $shipData = json_decode($shipRes->getBody()->getContents(), true);

        if (empty($shipData['rates'])) {
            throw new \Exception('Shippo returned no rates for this shipment.');
        }

        // Pick cheapest available rate
        $rates = collect($shipData['rates'])->filter(fn($r) => $r['object_state'] === 'VALID');
        $rate  = $rates->sortBy(fn($r) => (float) $r['amount'])->first();

        // Purchase label
        $txRes  = $client->post('transactions/', ['headers' => $headers, 'json' => [
            'rate'            => $rate['object_id'],
            'label_file_type' => 'PDF_4X6',
            'async'           => false,
        ]]);
        $txData = json_decode($txRes->getBody()->getContents(), true);

        if (($txData['status'] ?? '') !== 'SUCCESS') {
            throw new \Exception('Shippo label purchase failed: ' . ($txData['messages'][0]['text'] ?? 'unknown error'));
        }

        $shipment->shippo_transaction_id = $txData['object_id'];
        $shipment->label_url             = $txData['label_url'];
        if (!$shipment->tracking_number && !empty($txData['tracking_number'])) {
            $shipment->tracking_number = $txData['tracking_number'];
        }
        $shipment->save();

        return [
            'status'       => 'success',
            'type'         => 'us_shippo',
            'label_url'    => $txData['label_url'],
            'tracking'     => $txData['tracking_number'] ?? null,
            'carrier'      => $rate['provider']          ?? null,
            'service'      => $rate['servicelevel']['name'] ?? null,
            'rate_amount'  => $rate['amount']             ?? null,
            'rate_currency'=> $rate['currency']           ?? null,
        ];
    }

    private function generateColombiaLabel(Shipment $shipment): array
    {
        $data = [
            'shipment'         => $shipment,
            'destinationCity'  => $shipment->destinationCity->name    ?? '',
            'destinationState' => $shipment->destinationState->name   ?? '',
            'destinationCountry' => $shipment->destinationCountry->name ?? 'Colombia',
        ];

        $pdf = PDF::loadView('Export.pdf.colombia_label', $data);
        // 4×6 inches in points: 288 × 432
        $pdf->setPaper([0, 0, 288, 432]);

        $path    = 'labels/shipment-' . $shipment->id . '.pdf';
        $pdfPath = storage_path('app/' . $path);

        if (!is_dir(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0755, true);
        }

        $pdf->save($pdfPath);

        $publicUrl = url('/api/shipping/' . $shipment->id . '/download-label');
        $shipment->label_url = $publicUrl;
        $shipment->save();

        return [
            'status'    => 'success',
            'type'      => 'colombia_pdf',
            'label_url' => $publicUrl,
        ];
    }
}
