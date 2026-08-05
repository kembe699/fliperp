<?php

namespace App\Http\Requests\CrmActivity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(['call', 'email', 'meeting', 'note', 'complaint', 'follow_up'])],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activity_date' => ['sometimes', 'required', 'date'],
        ];
    }
}
