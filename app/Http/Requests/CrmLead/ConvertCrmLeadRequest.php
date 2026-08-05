<?php

namespace App\Http\Requests\CrmLead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            // The customers table requires a phone number, but a lead's own
            // phone is optional — this lets whoever converts the lead supply
            // one at conversion time if it was never captured on the lead.
            'phone' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', Rule::in(['walk_in', 'regular', 'credit'])],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
