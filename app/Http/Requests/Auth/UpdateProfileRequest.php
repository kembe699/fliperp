<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            // Not `current_password` (Laravel's built-in rule) — that resolves against
            // the default auth guard, which this Sanctum API doesn't map in
            // config/auth.php (see RoleService::paginate()'s comment on the same
            // landmine). AuthController checks it manually with Hash::check instead.
            'current_password' => ['required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }
}
