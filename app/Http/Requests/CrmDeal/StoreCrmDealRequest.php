<?php

namespace App\Http\Requests\CrmDeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'lead_id' => [
                'required_without:customer_id', 'nullable',
                Rule::exists('crm_leads', 'id')->where('company_id', $companyId),
            ],
            'customer_id' => [
                'required_without:lead_id', 'nullable',
                Rule::exists('customers', 'id')->where('company_id', $companyId),
            ],
            'pipeline_stage_id' => ['required', Rule::exists('crm_pipeline_stages', 'id')->where('company_id', $companyId)],
            'crm_service_id' => ['nullable', Rule::exists('crm_services', 'id')->where('company_id', $companyId)],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ];
    }
}
