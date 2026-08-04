<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:companies,slug'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
