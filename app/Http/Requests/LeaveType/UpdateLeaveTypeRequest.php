<?php

namespace App\Http\Requests\LeaveType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'days_per_year' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
        ];
    }
}
