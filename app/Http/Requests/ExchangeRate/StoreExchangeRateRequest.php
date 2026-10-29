<?php

namespace App\Http\Requests\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRateRequest extends FormRequest
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
        return [
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => [
                'required', 'date',
                Rule::unique('exchange_rates', 'effective_date')
                    ->where('company_id', $this->user()->company_id)
                    ->where('currency_code', strtoupper((string) $this->input('currency_code'))),
            ],
        ];
    }

    public function messages(): array
    {
        return ['effective_date.unique' => 'A rate for this currency already exists on that date; edit it instead.'];
    }
}
