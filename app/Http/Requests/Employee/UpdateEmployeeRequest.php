<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $employee = $this->route('employee');

        return [
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'department_id' => ['sometimes', 'required', Rule::exists('departments', 'id')->where('company_id', $companyId)],
            'position_id' => ['sometimes', 'required', Rule::exists('positions', 'id')->where('company_id', $companyId)],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'employee_code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_code')->where('company_id', $companyId)->ignore($employee),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'hire_date' => ['sometimes', 'required', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_type' => ['sometimes', 'required', Rule::in(['full_time', 'part_time', 'contract'])],
            'status' => ['nullable', Rule::in(['active', 'on_leave', 'terminated'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
