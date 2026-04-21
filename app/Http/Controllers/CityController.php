<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Exception;

class CityController extends Controller
{
   
    public function getCityState(Request $request)
    {
        try {
            $state_id = $request->state_id;

            $query = City::query();
            $query->where('state_id', $state_id);
            $citys = $query->select('id', 'name')->orderBy('name', 'ASC')->get();

            return response()->json($citys);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
