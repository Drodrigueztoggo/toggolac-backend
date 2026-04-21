<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreatePersonalShopperRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'first_name' => 'required',
            'phone_number' => 'nullable|numeric',
            'country_id' => 'required|integer',
            'password' => 'min:3|required_with:confirm_password|same:confirm_password',
            'confirm_password' => 'min:3',
            'email' => 'required|email|unique:personal_shoper,email'
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
