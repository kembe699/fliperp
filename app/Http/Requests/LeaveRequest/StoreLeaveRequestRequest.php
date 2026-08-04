<?php

namespace App\Http\Requests\LeaveRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $this->user()->company_id),
            ],
            'leave_type_id' => [
                'required',
                Rule::exists('leave_types', 'id')->where('company_id', $this->user()->company_id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
