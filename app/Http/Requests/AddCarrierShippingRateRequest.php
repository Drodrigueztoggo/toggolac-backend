<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use App\Models\CarrierShippingRate;

class AddCarrierShippingRateRequest extends FormRequest
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
            'carrier_id' => 'required|exists:carriers,id', // Reemplaza "carriers" con la tabla de transportistas real
            'country_id' => 'required|exists:countries,id', // Reemplaza "countries" con la tabla de países real
            'min_weight' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'max_weight' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/|gt:min_weight',
            'price' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'additional_charge' => 'numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que no exista una tarifa para el mismo país y transportista
            $carrierId = $this->input('carrier_id');
            $countryId = $this->input('country_id');

            $existingRate = CarrierShippingRate::where('carrier_id', $carrierId)
                ->where('country_id', $countryId)
                ->first();

            if ($existingRate) {
                $validator->errors()->add('country_id', 'Ya existe una tarifa para este país y transportista.');
            }
        });
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
