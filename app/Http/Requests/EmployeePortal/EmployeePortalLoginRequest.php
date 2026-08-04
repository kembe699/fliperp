<?php

namespace App\Http\Requests\EmployeePortal;

use Illuminate\Foundation\Http\FormRequest;

class EmployeePortalLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
