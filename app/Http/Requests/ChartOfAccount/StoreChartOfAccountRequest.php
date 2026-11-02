<?php

namespace App\Http\Requests\ChartOfAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('chart_of_accounts', 'code')->where('company_id', $this->user()->company_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'parent_id' => [
                'nullable',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->user()->company_id),
            ],
            'is_active' => ['nullable', 'boolean'],
            // Posted to the ledger against 3900 Opening Balance Equity (ChartOfAccountService).
            'opening_balance' => ['sometimes', 'nullable', 'numeric', 'between:-999999999999,999999999999'],
            'opening_balance_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
