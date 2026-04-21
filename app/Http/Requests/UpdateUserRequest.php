<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {

        return [
            'name' => 'nullable|string',
            // 'email' => ['required', 'email', Rule::unique('users')->ignore($this->id)],

            'email' => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    // Agrega la condición para verificar deleted_at
                    $query->where('deleted_at', null);
    
                    // Si estás actualizando un usuario, exclúyelo de la regla unique
                    if ($this->id) {
                        $query->where('id', '!=', $this->id);
                    }
    
                    return $query;
                }),
            ],
            'last_name' => 'nullable|string',
            'personal_id' => 'nullable|string',
            // 'phone_number' => 'nullable|string',
            // 'phone_number' => 'required|string|unique:users,phone_number,' . $this->id,
            'phone_number' => [
                'required',
                'string',
                Rule::unique('users')->where(function ($query) {
                    // Agrega la condición para verificar deleted_at
                    $query->where('deleted_at', null);
    
                    // Si estás actualizando un usuario, exclúyelo de la regla unique
                    if ($this->id) {
                        $query->where('id', '!=', $this->id);
                    }
    
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
            'password' => 'nullable|string|min:8',
            'password_confirm' => 'nullable|required_with:password|same:password|min:8',
        

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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('password')) {
                $this->merge(['password' => Hash::make($this->input('password'))]);
            }
        });
    }

}