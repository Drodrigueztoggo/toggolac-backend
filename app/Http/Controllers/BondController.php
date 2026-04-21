<?php

namespace App\Http\Controllers;

use App\Models\Bond;
use App\Models\User;
use App\Models\PurchaseOrderHeader;
use Illuminate\Http\Request;
use Cknow\Money\Money;

class BondController extends Controller
{
    public function searchBond(Request $request){
        try {
            $currencyFunctions = new CurrencyController();
            $currency = $request->query('currency');
            $search = $request->bond;
            $user = $request->user;
            $code = explode("-", $search);

            $query = Bond::where('code','=',count($code) >1 ? $code[1] : $code[0])->with(
                "purchaseOrderHeaderApply"
            )->first();
            $queryUser = User::find($user);
            if (empty($query) || is_null($query)){
                return response()->json([
                    "data" => null,
                    "message" => "No se encontro el bono"
                ]);
            }
            if ($query->is_global){
                $valid = $query->purchaseOrderHeaderApply->where(function ($item) use($queryUser) {
                    return $item->pivot->email === $queryUser->email;
                });
                $apply = $this->validTotal($queryUser, $query->minimun_amount, $currencyFunctions);
                if($apply["valid"]){
                    if (count($valid) > 0){
                        return response()->json([
                            "data" => null,
                            "message" => "El bono ya se utilizo"
                        ]);
                    } else {
                        $this->setBondPurchase($query->id, $queryUser);
                        return response()->json([
                            "data" => [
                                "id" => $query->id,
                                "mini_shopping" => $query->minimun_amount,
                                "amount" => $query->value_bond,
                                "approximate" => $currencyFunctions->convertAmount('USD', 'COP', $query->value_bond)
                            ],
                            "message" => "Disfruta tu bono para tu primera compra"
                        ]);
                    }
                } else {
                    return response()->json([
                        "data" => null,
                        "message" => $apply["message"]
                    ]);
                }
            }else if (!$query->is_global && count($query->purchaseOrderHeaderApply) === 0) {
                $this->setBondPurchase($query->id, $queryUser);
                return response()->json([
                    "data" => [
                        "id" => $query->id,
                        "mini_shopping" => $query->minimun_amount,
                        "amount" => $query->value_bond,
                        "approximate" => $currencyFunctions->convertAmount('USD', 'COP', $query->value_bond)
                    ],
                    "message" => "Disfruta tu bono para tu primera compra"
                ]);
                
            }else if (!$query->is_global && count($query->purchaseOrderHeaderApply) > 0) {
                return response()->json([
                    "data" => null,
                    "message" => "El bono ya se utilizo"
                ]);
            }
            
        } catch (\Throwable $th) {
            return response()->json([
                "error" => $th->getMessage()
            ],500);
        }
    }

    private function setBondPurchase(int $bondId, User $user){
        try {
            $bond = Bond::findOrFail($bondId);
            $query = PurchaseOrderHeader::where('client_id', $user->id)->where('purchase_status_id', 1)->first();
            if(!is_null($query)){
                if($bond->is_global){
                    $bond->purchaseOrderHeaderApply()->attach($query->id, ["email"=>$user->email]);
                } else {
                    $bond->purchaseOrderHeaderApply()->attach($query->id);
                }
            }
        } catch (\Exception $th) {
            //throw $th;
        }
    }

    private function validTotal(User $user, int $amount, CurrencyController $currencyFunctions){
        try {
            $total = 0;
            $query = PurchaseOrderHeader::with(
                'purchaseOrderDetails.purchaseOrderDetailTax'
            )->where('client_id', $user->id)->where('purchase_status_id', 1)->first();
            foreach ($query->purchaseOrderDetails as $detail) {
                $decode = json_decode($detail->purchaseOrderDetailTax->taxes);
                $total += $decode->total;
            }
            $rest = $amount-$total;
            $aproximate = Money::COP($currencyFunctions->convertAmount('USD', 'COP', $rest));
            return  [
                "valid" => $amount < $total,
                "message" => "Agrega otro producto en tu compra para usar este cupón. Te faltan USD ".Money::USD($rest).", aproximadamente COP $".number_format($aproximate->getAmount() / 100, 0, '.', ',').""
            ];
        } catch (\Exception $th) {
            dd($th);
        }
    }
}
