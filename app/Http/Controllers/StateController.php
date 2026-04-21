<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\State;
use Exception;
use Illuminate\Http\Request;

class StateController extends Controller
{
    public function getStateCountry(Request $request)
    {
        try {
            $country_id = $request->query('country_id');

            $query = State::where('country_id', $country_id); // Inicia una consulta al modelo Country

            $countries = $query->select('id', 'state_code', 'name')->orderBy('name', 'ASC')->get(); // Ejecuta la consulta y obtén los resultados

            return response()->json($countries);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
