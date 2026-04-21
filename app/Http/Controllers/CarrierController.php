<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCarrierRequest;
use App\Http\Requests\UpdateCarrierRequest;
use App\Models\Carrier;
use Illuminate\Http\Request;
use Exception;

class CarrierController extends Controller
{
    public function getCarrierList()
    {
        try {
            $carriers = Carrier::select('id', 'name', 'country_id')->get();
            return response()->json([
                'data' => $carriers
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCarriers(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 20);

            $query = Carrier::with('country')->select('id', 'name', 'country_id');

            $carriers = $query->paginate($perPage);

            return response()->json($carriers);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addCarrier(AddCarrierRequest $request)
    {
        try {
            $carrierData = $request->validated();

            $carrier = Carrier::create($carrierData);

            return response()->json([
                'carrier' => $carrier,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showCarrier(Request $request)
    {
        try {
            $carrier = Carrier::with('country','shippingRates')->select('id', 'name', 'country_id')->findOrFail($request->id);

            return response()->json($carrier);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCarrier(UpdateCarrierRequest $request)
    {
        try {
            $carrier = Carrier::findOrFail($request->id);

            $carrierData = $request->validated();

            $carrier->update($carrierData);

            return response()->json($carrier, 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteCarrier($id)
    {
        try {
            $carrier = Carrier::findOrFail($id);

            $carrier->delete();

            return [
                'status' => 'success',
                'message' => 'Carrier deleted successfully'
            ];
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
