<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AddCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // 'name_category' => 'required|string|max:85|unique:categories,name_category',
            'name_category' => 'required|string|max:85|unique:categories,name_category,NULL,id,deleted_at,NULL',
            'description_category' => 'required|string|max:1000',
            'image_category' => 'required|image|mimes:webp,jpeg,png,jpg,gif|max:500'
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
