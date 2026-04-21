<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ReceptionCenterRequest extends FormRequest
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
            'purchase_id' => 'required|integer',
            'products' => 'required|array',
            'products.*.purchase_product_id' => 'required|integer',
            'products.*.optimal_conditions_product' => 'required|boolean',
            'products.*.verified_quantity' => 'required|boolean',
            'products.*.conditions_brand' => 'required|boolean',
            'products.*.invoice_order' => 'nullable|boolean',
            'products.*.comment' => 'nullable|string',
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
