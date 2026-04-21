<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->id; // Get the ID from the request

        return [
            'id' => 'required|integer|exists:brands,id', // Validate the ID
            // 'name_brand' => 'required|string',
            'name_brand' => [
                'required',
                'string',
                'max:85',
                Rule::unique('brands', 'name_brand')->ignore($id, 'id')->whereNull('deleted_at')
            ],
            'country_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
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