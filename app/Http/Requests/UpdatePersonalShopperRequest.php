<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdatePersonalShopperRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->request->get("id");

        return [
            'first_name' => 'required',
            'phone_number' => 'nullable|numeric',
            'country_id' => 'required|integer',
            'password' => 'nullable|same:confirm_password',
            'confirm_password' => 'nullable',
            'email' => 'required|email|unique:personal_shoper,email,'.$id
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
