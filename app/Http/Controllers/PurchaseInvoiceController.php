<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseInvoiceRequest;
use App\Models\Commission;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderHeader;
use App\Models\PurchaseOrderHeaderLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends Controller
{
    public function addPurchaseInvoice(PurchaseInvoiceRequest $request)
    {
        try {
            DB::beginTransaction();
            $requestData = $request->all();

            $authenticatedUser = Auth::user(); // Obtener el usuario autenticado

            $infoPurchase = PurchaseOrderHeader::with(
                'purchaseOrderDetails:id,purchase_order_header_id',
                'purchaseOrderDetails.receptionCenterDetail:id,status,purchase_product_id'
            )->with(['infoTransaction' => function ($q) {
                $q->where('status', 'PAID')->select('id', 'purchase_order_id', 'amount');
            }])
                ->where('id', $requestData['purchase_id'])
                ->whereNotIn('purchase_status_id', [2, 7, 3, 4]) //LA ORDEN DE COMPRA NO PUEDE ESTAR COMPLETADA, ENVIADA, FALLIDA, DEVOLUCIÓN
                ->select('id', 'purchase_status_id', 'shipment_price', 'personal_shopper_id')->first();

            if ($infoPurchase) {

                if (!isset($infoPurchase['infoTransaction']['amount'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se puede realizar el cargue de facturas, el pago del usuario no ha sido confirmado.'
                    ], 403);
                }

                // $purchaseOrderDetails = $infoPurchase->purchaseOrderDetails;

                // if ($purchaseOrderDetails->every(function ($purchaseOrderDetail) {
                //     $receptionCenterDetail = $purchaseOrderDetail->receptionCenterDetail;

                //     return $receptionCenterDetail != null && ($receptionCenterDetail->status === 'Aceptado');
                // })) {
                //SI TODO ESTA OK SE CARGAN LAS FACTURAS
                if (isset(($requestData['file']))) {
                    foreach ($requestData['file'] as $file) {
                        $filePath = $this->storeImage($file);

                        $saveInvoice = new PurchaseInvoice([
                            'purchase_id' => $requestData['purchase_id'],
                            'file' => $filePath['path'],
                            'extension' => $filePath['extension'],
                            'user_id' => $authenticatedUser->id,
                        ]);

                        $saveInvoice->save();
                    }
                }


                //Y SE DEBE ACTUALIZAR EL ESTADO DELA ORDEN A COMPLETADO, TAMBIEN CALCULAR LA COMISION DEL USUARIO

                //LOG DE LA ORDEN DE COMPRA, CAMBIO DE ESTADO
                // PurchaseOrderHeaderLog::create([
                //     "purchase_order_id" => $requestData['purchase_id'],
                //     "previous_status_id" => $infoPurchase->purchase_status_id,
                //     "description" => "Orden de compra completada",
                //     "status_id" => 2,
                //     "user_id" => $authenticatedUser->id

                // ]);

                // $infoPurchase->update([
                //     'purchase_status_id' => 2
                // ]);


                // COMISION DEL USUARIO


                try {


                    if (isset($infoPurchase['infoTransaction']['amount'])) {
                        // HAY INFORMACION DEL PAGO DEL USUARIO
                        //DESCUENTO DEL VALOR DEL ENVÍO
                        $commissionAmount = ($infoPurchase['infoTransaction']['amount'] - (isset($infoPurchase->shipment_price) ? $infoPurchase->shipment_price : 0)) * 0.10;
                        // return $commissionAmount;
                        $personal_shopper_id = $infoPurchase->personal_shopper_id;


                        // Crea un nuevo registro en la tabla 'commissions'
                        $commission = Commission::updateOrInsert(
                            ['purchase_order_id' => $requestData['purchase_id']],
                            [
                                'user_id' => $personal_shopper_id,
                                'amount' => $commissionAmount,
                                'received_by_shopper' => false,
                                'received_date' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }


                    // return $updateOrderStatus->personal_shopper_id;
                } catch (\Exception $e) {
                    //throw $th;
                }
                // } else {
                //     return response()->json([
                //         'status' => 'error',
                //         'message' => 'No se puede realizar el cargue de facturas, aún hay productos por verificar.'
                //     ], 403);
                // }
            } else {
                return response()->json([
                    'message' => 'No es posible adjuntar la factura, el estado de la orden no lo permite'
                ], 403);
            }


            // return $infoPurchase;

            DB::commit();


            return response()->json(['status' => 'success'], 201);
        } catch (Exception $e) {
            dd($e);
            DB::rollback();


            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function storeImage($image)
    {
        $extension = $image->getClientOriginalExtension();

        $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $extension;
        $imagePath = $image->storeAs('uploads/purchase_files/' . now()->format('Y-m-d'), $imageName, 'public');

        return [
            'path' => $imagePath,
            'extension' => $extension,
        ];
    }
}
