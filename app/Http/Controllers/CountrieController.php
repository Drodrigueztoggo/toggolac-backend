<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Exception;

class CountrieController extends Controller
{
    
    
    public function getCountrie(Request $request)
    {
        try {
            $name = $request->query('name'); // Obtén el valor del parámetro 'name' de la URL
            
            $query = Country::where('active', true); // Inicia una consulta al modelo Country
            
            if ($name) {
                $query->where('name', 'like', "%$name%"); // Aplica el filtro si se proporciona el nombre
            }
    
            $countries = $query->select('id', 'iso2', 'name')->orderBy('name', 'ASC')->get(); // Ejecuta la consulta y obtén los resultados
    
            return response()->json($countries);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
