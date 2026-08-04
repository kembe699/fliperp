<?php

namespace App\Http\Requests\SalaryStructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $this->user()->company_id),
            ],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'allowances.*' => ['numeric'],
            'effective_date' => [
                'required', 'date',
                Rule::unique('salary_structures', 'effective_date')->where('employee_id', $this->input('employee_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_date.unique' => 'This employee already has a salary structure effective on that date.',
        ];
    }
}
