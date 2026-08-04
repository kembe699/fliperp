<?php

namespace App\Http\Requests\SalaryStructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $salaryStructure = $this->route('salary_structure');
        $employeeId = $this->input('employee_id', $salaryStructure?->employee_id);

        return [
            'employee_id' => [
                'sometimes', 'required',
                Rule::exists('employees', 'id')->where('company_id', $this->user()->company_id),
            ],
            'basic_salary' => ['sometimes', 'required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'allowances.*' => ['numeric'],
            'effective_date' => [
                'sometimes', 'required', 'date',
                Rule::unique('salary_structures', 'effective_date')
                    ->where('employee_id', $employeeId)
                    ->ignore($salaryStructure),
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
