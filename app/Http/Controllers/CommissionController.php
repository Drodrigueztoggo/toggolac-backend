<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Cknow\Money\Money as MoneyConvert;

class CommissionController extends Controller
{
    public function getCommisions(Request $request)
    {


        try {

            $perPage = $request->query('per_page', 20);

            $commissions = Commission::with(
                'personalShopper',
            )->paginate($perPage);


            $commisionFormat = $commissions->map(function ($commission) {
                return [
                    "id" => $commission->id, //ID DE LA COMISION
                    "user_id" => $commission->user_id, //ID DEL USUARIO
                    "amount" => MoneyConvert::USD($commission->amount),
                    "received_by_shopper" => $commission->received_by_shopper, 
                    "received_date" => isset($commission->received_date) ? Carbon::parse($commission->received_date)->format('Y-m-d H:i:s') : null, //FECHA EN QUE EL PERSONLA SHOPPER RECIBE EL DINERO
                    "created_at" =>  Carbon::parse($commission->created_at)->format('Y-m-d H:i:s'), //FECHA DE CREACION
                    "personal_shopper" => isset($commission->personalShopper) ? [
                        "id" => $commission->personalShopper->id,
                        "name" => $commission->personalShopper->name
                    ] : null

                ];
            });


            $response = [
                "data" => $commisionFormat,
                'current_page' => $commissions->currentPage(),
                'first_page_url' => $commissions->url(1),
                'from' => $commissions->firstItem(),
                'last_page' => $commissions->lastPage(),
                'last_page_url' => $commissions->url($commissions->lastPage()),
                'next_page_url' => $commissions->nextPageUrl(),
                'path' => $commissions->url($commissions->currentPage()),
                'per_page' => $commissions->perPage(),
                'prev_page_url' => $commissions->previousPageUrl(),
                'to' => $commissions->lastItem(),
                'total' => $commissions->total(),
            ];

            return $response;
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción de base de datos

            dd($e);
            // Manejar el error según tus necesidades

        }
    }
}
