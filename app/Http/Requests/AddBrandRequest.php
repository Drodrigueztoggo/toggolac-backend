<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AddBrandRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // 'name_brand' => 'required|string',
            'name_brand' => 'required|string|unique:brands,name_brand,NULL,id,deleted_at,NULL',
            'country_id' => 'required|integer',
            'city_id' => 'required|integer',
            'description_brand' => 'nullable|string',
            'mall_id' => 'nullable|integer',
            'store_mall_id' => 'nullable|integer',
            'image_brand' => 'nullable|image|mimes:webp,jpeg,png,jpg,gif|max:500',
            'selected_categories' => 'nullable|array',
            'selected_categories.*' => 'integer|exists:categories,id',
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