<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class HelpCenterCreateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'purchase_id' => 'nullable|integer|exists:purchase_order_headers,id',
            'request_type' => 'required|string|max:80',
            'reason' => 'required|string|max:80',
            'product' => 'nullable|string|max:100',
            'personal_shopper_id' => 'nullable|integer',
            'petition' => 'required|string',
            'image.*' => 'nullable|image|mimes:webp,jpeg,png,jpg,gif',
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
