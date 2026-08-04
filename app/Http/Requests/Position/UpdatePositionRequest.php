<?php

namespace App\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => [
                'sometimes', 'required',
                Rule::exists('departments', 'id')->where('company_id', $this->user()->company_id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'gte:min_salary'],
        ];
    }
}
