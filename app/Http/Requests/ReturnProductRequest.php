<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ReturnProductRequest extends FormRequest
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
            'purchase_id' => 'required|integer|exists:purchase_order_headers,id',
            'products.*.purchase_order_detail_id' => 'required|integer|exists:purchase_order_details,id',
            'products.*.return_reason' => 'required|string',
            'products.*.amount' => 'required|integer',
            'products.*.return_images.*' => 'image|mimes:webp,jpeg,png,jpg,gif|max:500', // Validación de imágenes
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
