<?php

namespace App\Http\Requests\StatutoryDeductionRule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatutoryDeductionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rule = $this->route('statutory_deduction_rule');
        $calculationType = $this->input('calculation_type', $rule?->calculation_type);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['tax', 'pension', 'other'])],
            'calculation_type' => ['sometimes', 'required', Rule::in(['percentage', 'fixed', 'bracket'])],
            'country_code' => ['sometimes', 'required', 'string', 'max:3'],
            'is_active' => ['nullable', 'boolean'],

            'config' => ['sometimes', 'required', 'array'],
            'config.rate' => [Rule::requiredIf($calculationType === 'percentage' && $this->has('config')), 'numeric', 'min:0', 'max:1'],
            'config.amount' => [Rule::requiredIf($calculationType === 'fixed' && $this->has('config')), 'numeric', 'min:0'],
            'config.brackets' => [Rule::requiredIf($calculationType === 'bracket' && $this->has('config')), 'array', 'min:1'],
            'config.brackets.*.min' => ['required_with:config.brackets', 'numeric', 'min:0'],
            'config.brackets.*.max' => ['nullable', 'numeric', 'min:0'],
            'config.brackets.*.rate' => ['required_with:config.brackets', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
