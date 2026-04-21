<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AddOfferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required',
            'description' => 'required',
            'discount_percentage_from' => 'required|integer',
            'discount_percentage_to' => 'nullable|integer',
            'discount_price_from' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'discount_price_to' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'image_offert' => 'nullable',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'country_id' => 'nullable|integer|exists:countries,id',
            'mall_id' => 'nullable|integer|exists:malls,id',
            'store_mall_id' => 'nullable|integer|exists:store_malls,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'product_id' => 'nullable|integer|exists:products,id',
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