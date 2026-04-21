<?php

namespace App\Http\Controllers;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Cknow\Money\Money as MoneyConvert;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function getTransactions(Request $request)
    {


        try {

            $filter_start_date = $request->query('start_date');
            $filter_end_date = $request->query('end_date');
            $filter_price_min = $request->query('price_min');
            $filter_price_max = $request->query('price_max');
            $no_paginate = $request->query('no_paginate');
            $per_page = $request->query('per_page', 20);

            $filter_transaction_id = $request->query('transaction_id');
            $filter_client_id = $request->query('client_id');
            $filter_personal_shopper_id = $request->query('personal_shopper_id');


            $transactionsQuery = Transaction::with(
                'infoOrder:id,client_id,destination_country_id,destination_city_id,personal_shopper_id',
                'infoOrder.personalShopper:id,name,last_name',
                'infoOrder.client:id,name,last_name',
                'infoOrder.destinationCountry',
                'infoOrder.destinationCity',
                'infoOrder.purchaseOrderDetails:id,purchase_order_header_id,product_id',
                'infoOrder.purchaseOrderDetails.product:id,name_product',
            )->select('id', 'payment_id', 'payment_method_type', 'status', 'amount', 'created_date', 'approved_date', 'purchase_order_id');

            if ($filter_start_date) {
                $transactionsQuery->whereDate('created_date', '>=', $filter_start_date);
            }

            if ($filter_end_date) {
                $transactionsQuery->whereDate('created_date', '<=', $filter_end_date);
            }

            if (isset($filter_price_min) && isset($filter_price_max)) {
                $transactionsQuery->whereBetween('amount', [$filter_price_min, $filter_price_max]);
            }

            if (isset($filter_transaction_id)) {
                $transactionsQuery->where('id', $filter_transaction_id);
            }
            if (isset($filter_client_id)) {
                $transactionsQuery->whereHas('infoOrder', function ($query) use ($filter_client_id) {
                    $query->where('client_id', $filter_client_id);
                });
            }
            if (isset($filter_personal_shopper_id)) {
                $transactionsQuery->whereHas('infoOrder', function ($query) use ($filter_personal_shopper_id) {
                    $query->where('personal_shopper_id', $filter_personal_shopper_id);
                });
            }


            if ($no_paginate) {
                $transactions = $transactionsQuery->orderBy('created_at', 'desc')->get();
            } else {
                $transactions = $transactionsQuery->orderBy('created_at', 'desc')->paginate($per_page);
            }

            $formattedTransactions = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'payment_id' => $transaction->payment_id,
                    'payment_method_type' => $transaction->payment_method_type,
                    'status' => $transaction->status,
                    'created_date' => $transaction->created_date,
                    'approved_date' => $transaction->approved_date,
                    'order_id' => $transaction->order_id,
                    'info_order' => isset($transaction->infoOrder) ? [
                        "id" => $transaction->infoOrder->id,
                        "products" => $transaction->infoOrder->purchaseOrderDetails,
                        "client" => $transaction->infoOrder->client,
                        "personal_shopper" => $transaction->infoOrder->personalShopper,
                        "destination_country" => $transaction->infoOrder->destinationCountry,
                        "destination_city" => $transaction->infoOrder->destinationCity,
                    ] : null,
                    'amount' => MoneyConvert::USD($transaction->amount),
                ];
            });



            if ($no_paginate) {
                $data = $formattedTransactions;
            } else {

                $data = [
                    "data" => $formattedTransactions,
                    'current_page' => $transactions->currentPage(),
                    'first_page_url' => $transactions->url(1),
                    'from' => $transactions->firstItem(),
                    'last_page' => $transactions->lastPage(),
                    'last_page_url' => $transactions->url($transactions->lastPage()),
                    'next_page_url' => $transactions->nextPageUrl(),
                    'path' => $transactions->url($transactions->currentPage()),
                    'per_page' => $transactions->perPage(),
                    'prev_page_url' => $transactions->previousPageUrl(),
                    'to' => $transactions->lastItem(),
                    'total' => $transactions->total(),
                ];
            }

            return $data;
        } catch (\Exception $e) {
            dd($e);
            // Manejar cualquier excepción y devolver una respuesta de error
            return response()->json(['error' => 'Ocurrió un error en el servidor.'], 500);
        }
    }


    public function downloadTransactionsExcel(Request $request)
    {
        try {
            $filter_start_date = $request->start_date;
            $filter_end_date = $request->end_date;

            $requestFunctionPurchase = new Request([
                'start_date' => Carbon::parse($filter_start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($filter_end_date)->format('Y-m-d'),
                'no_paginate' => true
            ]);



            $transactions = $this->getTransactions($requestFunctionPurchase);

            // return $transactions;
            return Excel::download(new TransactionsExport($transactions), 'transactions.xlsx'); // Nombre del archivo Excel

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
