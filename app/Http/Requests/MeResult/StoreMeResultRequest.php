<?php

namespace App\Http\Requests\MeResult;

use App\Models\MeProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectIds = MeProject::query()->where('company_id', $this->user()->company_id)->pluck('id');

        return [
            'me_indicator_id' => [
                'required',
                Rule::exists('me_indicators', 'id')->where(
                    fn ($query) => $query->whereIn('me_project_id', $projectIds)
                ),
            ],
            'reporting_period' => ['required', 'string', 'max:100'],
            'actual_value' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
