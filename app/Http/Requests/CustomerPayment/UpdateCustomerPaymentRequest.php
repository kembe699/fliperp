<?php

namespace App\Http\Requests\CustomerPayment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
