<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('companies', 'slug')->ignore($company)],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
