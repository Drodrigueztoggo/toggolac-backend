<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;


class AddProductRequest extends FormRequest
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
            // 'name_product' => 'required|string',
            'name_product' => 'required|string|unique:products,name_product,NULL,id,deleted_at,NULL',
            'description_product' => 'nullable|string',
            'price_from' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'price_to' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'weight' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/',
            'brand_id' => 'nullable|exists:brands,id',
            // 'mall_id' => 'nullable|exists:malls,id',
            'image_product' => 'nullable|image|mimes:webp,jpeg,png,jpg,gif|max:500',
            'selected_categories' => 'array',
            'selected_categories.*' => 'exists:categories,id',
            'selected_stores' => 'array',
            'selected_stores.*' => 'exists:store_malls,id',
            'selected_malls' => 'array',
            'selected_malls.*' => 'exists:malls,id',
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
