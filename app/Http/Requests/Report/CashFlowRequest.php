<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class CashFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }
}
