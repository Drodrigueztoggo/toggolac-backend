<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'purchase_order_id' => 'required|integer',
            'carrier_id' => 'required|integer',
            'origin_address' => 'required|string',
            'destination_address' => 'required|string',
            'customer_name_lastname' => 'required|string',
            'origin_country_id' => 'required|integer',
            'origin_state_id' => 'required|integer',
            'origin_city_id' => 'required|integer',
            'destination_country_id' => 'required|integer',
            'destination_state_id' => 'required|integer',
            'destination_city_id' => 'required|integer',
            'tracking_number' => 'nullable|string',
            'date' => 'required|date',
            'origin_postal_code' => 'string',
            'destination_postal_code' => 'string',
            'pounds_weight' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'total_shipping_cost' => 'numeric|regex:/^\d+(\.\d{1,2})?$/'
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
