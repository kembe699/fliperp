<?php

namespace App\Http\Requests\CrmDeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCrmDealStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'pipeline_stage_id' => ['required', Rule::exists('crm_pipeline_stages', 'id')->where('company_id', $companyId)],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
