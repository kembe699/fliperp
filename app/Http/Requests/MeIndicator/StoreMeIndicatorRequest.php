<?php

namespace App\Http\Requests\MeIndicator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'me_project_id' => [
                'required',
                Rule::exists('me_projects', 'id')->where('company_id', $this->user()->company_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit_of_measure' => ['nullable', 'string', 'max:100'],
            'target_value' => ['required', 'numeric'],
            'baseline_value' => ['nullable', 'numeric'],
        ];
    }
}
