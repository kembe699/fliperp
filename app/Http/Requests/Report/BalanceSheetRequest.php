<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'as_of' => ['required', 'date'],
            'allow_future' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowFuture = filter_var($this->input('allow_future', false), FILTER_VALIDATE_BOOLEAN);

            if (! $allowFuture && $this->filled('as_of') && $this->date('as_of')?->isAfter(now()->endOfDay())) {
                $validator->errors()->add('as_of', 'The as_of date cannot be in the future unless allow_future is set.');
            }
        });
    }
}
