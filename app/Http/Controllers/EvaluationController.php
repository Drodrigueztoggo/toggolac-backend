<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvaluationRequest;
use App\Models\Evaluation;
use App\Models\EvaluationsPersonalShoper;
use App\Models\EvaluationsProduct;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseOrderHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function getEvaluations(Request $request)
    {
        try {
            //code...
        } catch (\Exception $e) {
            //throw $th;
            dd($e);
        }
    }

    public function addEvaluation(StoreEvaluationRequest $request)
    {
        try {
            DB::beginTransaction();
            $createUserId = auth()->user()->id;

            $validateEvaluation = Evaluation::where('user_id', $createUserId)->where('purchase_order_id', $request->purchase_order_id)->count();
            //SI YA HAY UNA EVALUACIÓN REGISTRADA NO SE PUEDE VOLVER A CREAR
            if ($validateEvaluation > 0) {
                return response()->json([
                    'message' => 'No es posible registrar una nueva calificación para esta misma orden de compra'
                ], 403);
            }

            $purchase = PurchaseOrderHeader::where('id', $request->purchase_order_id)->where('purchase_status_id', 2)->select('id', 'personal_shopper_id')->first();

            if (isset($purchase)) {

                $products = PurchaseOrderDetail::where('purchase_order_header_id', $request->purchase_order_id)->select('id', 'product_id')->get();

                $productsiDs = $products->pluck('product_id')->toArray();

                //GUARDAR EVALUACION
                $saveEvaluation = new Evaluation();
                $saveEvaluation->user_id = $createUserId;
                $saveEvaluation->purchase_order_id = $request->purchase_order_id;
                $saveEvaluation->general_rating = $request->general_rating;
                $saveEvaluation->delivery_time = $request->delivery_time;
                $saveEvaluation->product_quality = $request->product_quality;
                $saveEvaluation->customer_service = $request->customer_service;
                $saveEvaluation->store_navigation = $request->store_navigation;
                $saveEvaluation->payment_process = $request->payment_process;
                $saveEvaluation->review = $request->review;
                $saveEvaluation->save();

                //GUARDAR CALIFICACION DEL SHOPPER
                $saveEvaluationShopper = new EvaluationsPersonalShoper();
                $saveEvaluationShopper->evaluation_id = $saveEvaluation->id;
                $saveEvaluationShopper->user_id = $purchase->personal_shopper_id;
                $saveEvaluationShopper->rating = $request->customer_service;
                $saveEvaluationShopper->save();

                //GUARDAR CALIFICACION DEL SHOPPER
                foreach ($productsiDs as $key => $productId) {
                    $saveEvaluationProduct = new EvaluationsProduct();
                    $saveEvaluationProduct->evaluation_id = $saveEvaluation->id;
                    $saveEvaluationProduct->product_id = $productId;
                    $saveEvaluationProduct->rating = $request->product_quality;
                    $saveEvaluationProduct->save();
                }
            } else {
                return response()->json([
                    'message' => 'No se ha encontrado la orden de compra o posiblemente no se encuentra completada'
                ], 403);
            }

            DB::commit();

            return response()->json([
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
