<?php

namespace App\Http\Requests\CrmPipelineStage;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmPipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:1'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_closed_won' => ['sometimes', 'boolean'],
            'is_closed_lost' => ['sometimes', 'boolean'],
        ];
    }
}
