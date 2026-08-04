<?php

namespace App\Http\Requests\EmployeePortal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deliberately has no employee_id field — the portal always creates leave
 * requests for the authenticated user's own linked employee, never one
 * supplied by the client, so there is no way to request on another
 * employee's behalf even by tampering with the payload.
 */
class StoreEmployeePortalLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
