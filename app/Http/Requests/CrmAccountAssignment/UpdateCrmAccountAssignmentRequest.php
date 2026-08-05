<?php

namespace App\Http\Requests\CrmAccountAssignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmAccountAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::in(['primary', 'support'])],
        ];
    }
}
