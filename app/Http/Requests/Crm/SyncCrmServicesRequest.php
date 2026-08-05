<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCrmServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'service_ids' => ['present', 'array'],
            'service_ids.*' => [
                'integer', 'distinct',
                Rule::exists('crm_services', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
