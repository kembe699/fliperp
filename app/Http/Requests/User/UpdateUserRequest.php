<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $companyId = $user->company_id ?? $this->user()->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()],
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
