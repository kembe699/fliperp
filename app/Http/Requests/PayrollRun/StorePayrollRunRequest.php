<?php

namespace App\Http\Requests\PayrollRun;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('company_id', $this->user()->company_id),
            ],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }
}
