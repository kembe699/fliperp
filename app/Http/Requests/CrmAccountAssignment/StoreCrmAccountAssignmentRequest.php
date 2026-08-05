<?php

namespace App\Http\Requests\CrmAccountAssignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmAccountAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'user_id' => ['required', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'role' => ['sometimes', Rule::in(['primary', 'support'])],
        ];
    }
}
