<?php

namespace App\Http\Requests\MeProject;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['planned', 'ongoing', 'completed'])],
            'budget_period_id' => [
                'nullable',
                Rule::exists('budget_periods', 'id')->where('company_id', $this->user()->company_id),
            ],
        ];
    }
}
