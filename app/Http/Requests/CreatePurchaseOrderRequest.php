<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Puedes definir la lógica de autorización aquí si es necesario.
    }

    public function rules()
    {
        return [
            // Campos para el encabezado (PurchaseOrderHeader)
            'id' => 'nullable|exists:purchase_order_headers,id',
            'client_id' => 'required|integer',
            'mall_id' => 'required|integer',
            // 'store_id' => 'required|integer',
            'personal_shopper_id' => 'required|integer',
            'destination_address' => 'required|string|max:45',
            'destination_country_id' => 'required|integer',
            'destination_state_id' => 'required|integer',
            'destination_city_id' => 'required|integer',
            'conveyor_id' => 'required|integer',
            'estimated_date' => 'nullable|date',
            // Campos para el detalle (PurchaseOrderDetail)
            'details.*.product_id' => 'required|integer',
            'details.*.price' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'details.*.store_id' => 'required|integer',
            'details.*.amount' => 'required|integer',
            'details.*.category_id' => 'required|integer',
            'details.*.image.*' => 'file',
            'details.*.weight' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/|max:45',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }
}
