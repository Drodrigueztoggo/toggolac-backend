<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StoreEvaluationRequest extends FormRequest
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
            'purchase_order_id' => 'required|numeric',
            'general_rating' => 'required|numeric|between:0,5',
            'delivery_time' => 'required|numeric|between:0,5',
            'product_quality' => 'required|numeric|between:0,5',
            'customer_service' => 'required|numeric|between:0,5',
            'store_navigation' => 'required|numeric|between:0,5',
            'payment_process' => 'required|numeric|between:0,5',
            'review' => 'nullable|string',
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
