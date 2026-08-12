<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_phone' => ['nullable', 'string', 'max:32'],
            'billing_phone' => ['nullable', 'string', 'max:32'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }
}
