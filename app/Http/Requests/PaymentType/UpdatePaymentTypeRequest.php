<?php

namespace App\Http\Requests\PaymentType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['cash', 'card', 'mobile_money', 'bank_transfer', 'credit'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
