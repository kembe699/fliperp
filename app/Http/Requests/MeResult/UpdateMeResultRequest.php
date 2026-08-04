<?php

namespace App\Http\Requests\MeResult;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reporting_period' => ['sometimes', 'required', 'string', 'max:100'],
            'actual_value' => ['sometimes', 'required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
