<?php

namespace App\Http\Requests\MeIndicator;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit_of_measure' => ['nullable', 'string', 'max:100'],
            'target_value' => ['sometimes', 'required', 'numeric'],
            'baseline_value' => ['nullable', 'numeric'],
        ];
    }
}
