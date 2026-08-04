<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('company_id', $this->user()->company_id),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('company_id', $this->user()->company_id),
            ],
        ];
    }
}
