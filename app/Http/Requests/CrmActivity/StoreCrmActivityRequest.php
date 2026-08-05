<?php

namespace App\Http\Requests\CrmActivity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => [
                'required_without_all:lead_id,deal_id', 'nullable',
                Rule::exists('customers', 'id')->where('company_id', $companyId),
            ],
            'lead_id' => [
                'required_without_all:customer_id,deal_id', 'nullable',
                Rule::exists('crm_leads', 'id')->where('company_id', $companyId),
            ],
            'deal_id' => [
                'required_without_all:customer_id,lead_id', 'nullable',
                Rule::exists('crm_deals', 'id')->where('company_id', $companyId),
            ],
            'type' => ['required', Rule::in(['call', 'email', 'meeting', 'note', 'complaint', 'follow_up'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activity_date' => ['required', 'date'],
        ];
    }
}
