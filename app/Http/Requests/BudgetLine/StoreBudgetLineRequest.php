<?php

namespace App\Http\Requests\BudgetLine;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'budget_period_id' => ['required', Rule::exists('budget_periods', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $companyId)],
            'account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'budgeted_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
