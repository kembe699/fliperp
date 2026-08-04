<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permission = $this->route('permission');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission),
            ],
        ];
    }
}
