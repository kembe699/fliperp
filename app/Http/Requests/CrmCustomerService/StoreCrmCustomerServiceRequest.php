<?php

namespace App\Http\Requests\CrmCustomerService;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmCustomerServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'crm_service_id' => ['required', Rule::exists('crm_services', 'id')->where('company_id', $companyId)],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where('company_id', $companyId)],
            'price_charged' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['active', 'completed', 'cancelled'])],
        ];
    }
}
