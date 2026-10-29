<?php

namespace App\Http\Requests\Grant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrantBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->user()->company_id)],
            'dea_id' => ['nullable', Rule::exists('grant_deas', 'id')->where('grant_id', $this->route('grant')->id)],
            'budgeted_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return ['dea_id.exists' => 'The DEA must belong to this SOF.'];
    }
}
