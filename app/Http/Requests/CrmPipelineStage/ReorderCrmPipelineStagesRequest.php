<?php

namespace App\Http\Requests\CrmPipelineStage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCrmPipelineStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('crm_pipeline_stages', 'id')->where('company_id', $companyId),
            ],
        ];
    }
}
