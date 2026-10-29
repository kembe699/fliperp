<?php

namespace App\Http\Requests\SupplierBill;

use App\Services\Grants\DimensionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $bill = $this->route('supplier_bill');

        return [
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('supplier_bills', 'reference_number')->where('company_id', $companyId)->ignore($bill),
            ],
            'bill_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'required', 'date', 'after_or_equal:bill_date'],
            'subtotal' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'expense_account_id' => [
                'nullable',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId),
            ],
            ...DimensionService::lineRules(),
        ];
    }
}
