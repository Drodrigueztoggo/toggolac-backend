<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCarrierShippingRateRequest;
use App\Http\Requests\UpdateCarrierShippingRateRequest;
use App\Models\CarrierShippingRate;
use Illuminate\Http\Request;
use Exception;

class CarrierShippingRateController extends Controller
{
    public function getCarrierShippingRates()
    {
        try {
            $carrierShippingRates = CarrierShippingRate::select('carrier_id', 'country_id', 'min_weight', 'max_weight', 'price', 'additional_charge')->get();
            return response()->json([
                'data' => $carrierShippingRates
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addCarrierShippingRate(AddCarrierShippingRateRequest $request)
    {
        try {
            $carrierShippingRateData = $request->validated();

            $groupRate = date('YmdHis'); // Genera un valor aleatorio para group_rate

            $carrierShippingRates = collect($carrierShippingRateData['country_id'])->map(function ($countryId) use ($carrierShippingRateData, $groupRate) {
                return array_merge($carrierShippingRateData, [
                    'country_id' => $countryId,
                    'group_rate' => $groupRate,
                ]);
            });

            $createdRates = CarrierShippingRate::insert($carrierShippingRates->toArray());

            if (!$createdRates) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al crear las tarifas de envío.'
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Tarifas de envío creadas exitosamente.'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showCarrierShippingRate(Request $request)
    {
        try {
            $carrierShippingRate = CarrierShippingRate::findOrFail($request->id);

            return response()->json($carrierShippingRate);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCarrierShippingRate(UpdateCarrierShippingRateRequest $request)
    {
        try {
            $carrierShippingRateData = $request->validated();


            if (isset($carrierShippingRateData['id'])) {

                // Actualizar solo el registro individual
                $carrierShippingRate = CarrierShippingRate::findOrFail($carrierShippingRateData['id']);
                $carrierShippingRateData['group_rate'] = date('YmdHis'); // Asignar un nuevo valor aleatorio a group_rate
                $carrierShippingRate->update($carrierShippingRateData);
            } elseif (isset($carrierShippingRateData['country_id'])) {
                // Actualizar o agregar registros en grupo
                $groupRate = $carrierShippingRateData['group_rate'];
                $countryIds = $carrierShippingRateData['country_id'];

                // Obtener las tarifas de envío existentes para el mismo carrier_id y group_rate
                $existingRates = CarrierShippingRate::where('carrier_id', $carrierShippingRateData['carrier_id'])
                    ->where('group_rate', $groupRate)
                    ->whereIn('country_id', $countryIds)
                    ->get();

                // Actualizar las tarifas de envío existentes
                foreach ($existingRates as $existingRate) {
                    // $carrierShippingRateData
                    $existingRate->update([
                        "min_weight" => $carrierShippingRateData['min_weight'],
                        "max_weight" => $carrierShippingRateData['max_weight'],
                        "price" => $carrierShippingRateData['price'],
                        "additional_charge" => $carrierShippingRateData['additional_charge']
                    ]);
                }

                // Crear nuevas tarifas de envío si no existen
                $newCountryIds = array_diff($countryIds, $existingRates->pluck('country_id')->toArray());
                $newRates = [];

                foreach ($newCountryIds as $newCountryId) {
                    $newRates[] = array_merge($carrierShippingRateData, [
                        'country_id' => $newCountryId,
                    ]);
                }

                if (!empty($newRates)) {
                    CarrierShippingRate::insert($newRates);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Se requiere el ID o la propiedad "country_id" para actualizar los registros.'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Tarifa de envío actualizada exitosamente.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function deleteCarrierShippingRate($id)
    {
        try {
            $carrierShippingRate = CarrierShippingRate::findOrFail($id);

            $carrierShippingRate->delete();

            return [
                'status' => 'success',
                'message' => 'Carrier shipping rate deleted successfully'
            ];
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
