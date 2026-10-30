<?php

namespace App\Http\Requests\Platform;

use App\Support\Modules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The client code is what the client's users type at login. It's stored
     * uppercase (login matches case-insensitively), so "nhc-sobkh " and
     * "NHC-SOBKH" are the same code — and the unique check sees them as one.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('client_code')) {
            $this->merge(['client_code' => strtoupper(trim((string) $this->input('client_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            // Letters and digits, optionally split by single hyphens (NHC-SOBKH, SCI-SSD).
            // Unique across soft-deleted companies too: a code is never reissued.
            'client_code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/', Rule::unique('companies', 'client_code')],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_phone' => ['nullable', 'string', 'max:32'],
            'billing_phone' => ['nullable', 'string', 'max:32'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'enabled_modules' => ['sometimes', 'array'],
            'enabled_modules.*' => ['string', Rule::in(Modules::keys())],
        ];
    }

    public function messages(): array
    {
        return [
            'client_code.regex' => 'The client code may only contain letters, numbers and single hyphens (e.g. NHC-SOBKH).',
            'client_code.unique' => 'This client code is already used by another client.',
        ];
    }
}
