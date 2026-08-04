<?php

namespace App\Http\Requests\SupplierPayment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierPaymentRequest extends FormRequest
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
