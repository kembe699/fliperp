<?php

namespace App\Http\Requests\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $journalEntry = $this->route('journal_entry');

        return [
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('company_id', $this->user()->company_id),
            ],
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('journal_entries', 'reference_number')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($journalEntry),
            ],
            'entry_date' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string'],
            'source_module' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'integer'],
            'lines' => ['sometimes', 'required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required_with:lines',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $this->user()->company_id),
            ],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
