<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class AddUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'nullable|string',
            // 'email' => 'required|email|unique:users,email',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    // Agrega la condición para verificar deleted_at
                    $query->where('deleted_at', null);
    
                    return $query;
                }),
            ],
            'role_id' => 'required|integer',
            'last_name' => 'nullable|string',
            'personal_id' => 'nullable|string',
            // 'phone_number' => 'nullable|string|unique:users,phone_number',
            'phone_number' => [
                'nullable',
                'string',
                Rule::unique('users')->where(function ($query) {
                    // Agrega la condición para verificar deleted_at
                    $query->where('deleted_at', null);
    
                    return $query;
                }),
            ],
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'country_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'postal_code' => 'nullable|string',
            'gender' => 'nullable|string',
            'image_user' => 'nullable|image|mimes:webp,jpeg,png,jpg,gif|max:500',
            'password' => 'required|string|min:8', // Include password validation
            'password_confirm' => 'required_with:password|same:password|min:8',
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