<?php

namespace App\Http\Requests\EmployeeContract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeContractRequest extends FormRequest
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
            'contract_type' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'document_url' => ['nullable', 'string', 'max:2048'],
            'contract_body' => ['nullable', 'string'],
        ];
    }
}
