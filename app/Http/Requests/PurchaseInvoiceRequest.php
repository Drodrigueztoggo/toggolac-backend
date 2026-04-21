<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseInvoiceRequest extends FormRequest
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
            'purchase_id' => 'required|integer|exists:purchase_order_headers,id',
            'file.*' => 'required|file|mimes:webp,jpeg,jpg,png,gif,bmp,pdf|max:500', // 500 kilobytes
        ];
    }
}
