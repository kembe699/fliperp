<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['cash', 'card', 'mobile_money', 'bank_transfer', 'credit'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
