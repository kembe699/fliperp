<?php

namespace App\Http\Requests\StatutoryDeductionRule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatutoryDeductionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['tax', 'pension', 'other'])],
            'calculation_type' => ['required', Rule::in(['percentage', 'fixed', 'bracket'])],
            'country_code' => ['required', 'string', 'max:3'],
            'is_active' => ['nullable', 'boolean'],

            'config' => ['required', 'array'],
            'config.rate' => ['required_if:calculation_type,percentage', 'numeric', 'min:0', 'max:1'],
            'config.amount' => ['required_if:calculation_type,fixed', 'numeric', 'min:0'],
            'config.brackets' => ['required_if:calculation_type,bracket', 'array', 'min:1'],
            'config.brackets.*.min' => ['required_with:config.brackets', 'numeric', 'min:0'],
            'config.brackets.*.max' => ['nullable', 'numeric', 'min:0'],
            'config.brackets.*.rate' => ['required_with:config.brackets', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
