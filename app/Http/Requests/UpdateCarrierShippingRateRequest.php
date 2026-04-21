<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateCarrierShippingRateRequest extends FormRequest
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
            'id'=> 'numeric',
            'carrier_id' => 'exists:carriers,id', // Reemplaza "carriers" con la tabla de transportistas real
            'country_id' => 'exists:countries,id', // Reemplaza "countries" con la tabla de países real
            'min_weight' => 'numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'max_weight' => 'numeric|regex:/^\d+(\.\d{1,2})?$/|gt:min_weight',
            'price' => 'numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'additional_charge' => 'numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'group_rate' => 'numeric'
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
