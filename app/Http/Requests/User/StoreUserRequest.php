<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
            'branch_id' => $companyId
                ? ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)]
                : ['prohibited'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where(
                fn ($query) => $companyId
                    ? $query->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
                    : $query, // platform super_admin (no company_id) may assign any role
            )],
        ];
    }
}
