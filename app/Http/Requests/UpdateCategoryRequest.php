<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->id; // Obtener el ID de la categoría de la URL


        return [
            // 'name_category' => 'required|string|max:85|unique:categories,name_category,' . $id,
            'name_category' => [
                'required',
                'string',
                'max:85',
                Rule::unique('categories', 'name_category')->ignore($id, 'id')->whereNull('deleted_at')
            ],
            'description_category' => 'required|string|max:1000',
            'image_category' => 'nullable|image|mimes:webp,jpeg,png,jpg,gif|max:500'
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
