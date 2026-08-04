<?php

namespace App\Http\Requests\MeActivity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'responsible_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed', 'delayed'])],
        ];
    }
}
