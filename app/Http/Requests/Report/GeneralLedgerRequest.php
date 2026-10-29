<?php

namespace App\Http\Requests\Report;

use App\Services\Grants\DimensionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneralLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            // Only lines coded to these (SOF, DEA, project, cost centre).
            ...DimensionService::filterRules(),
            // Show the (base-currency) figures converted into this currency, for display.
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
