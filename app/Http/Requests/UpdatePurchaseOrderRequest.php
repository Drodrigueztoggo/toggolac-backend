<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdatePurchaseOrderRequest extends FormRequest
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
            // 'mall_id' => 'required|integer',
            // 'store_id' => 'required|integer',
            // 'shipment_status' => 'required|string|max:20',
            // 'purchase_status_id' => 'required|integer',
            // 'personal_shopper_id' => 'required|integer',
            // 'destination_address' => 'required|string|max:45',
            // 'destination_country_id' => 'required|integer',
            // 'origin_city_id' => 'nullable|integer',
            // 'destination_city_id' => 'required|integer',
            // 'start_date' => 'nullable|date',
            'conveyor_id' => 'required|integer',
            'estimated_date' => 'nullable|date',
            'guide_number' => 'nullable|integer'
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
