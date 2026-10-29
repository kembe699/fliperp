<?php

namespace App\Http\Requests\Grant;

use App\Models\Grant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency_code')) {
            $this->merge(['currency_code' => strtoupper((string) $this->input('currency_code'))]);
        }
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $grant = $this->route('grant');
        $required = $grant ? ['sometimes', 'required'] : ['required'];

        return [
            'donor_id' => [...$required, Rule::exists('donors', 'id')->where('company_id', $companyId)],
            'code' => [...$required, 'string', 'max:50', Rule::unique('grants', 'code')->where('company_id', $companyId)->ignore($grant)],
            'name' => [...$required, 'string', 'max:255'],
            'currency_code' => [...$required, 'string', 'size:3', 'exists:currencies,code'],
            'start_date' => [...$required, 'date'],
            'end_date' => [...$required, 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(Grant::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Another SOF already has this code.'];
    }
}
