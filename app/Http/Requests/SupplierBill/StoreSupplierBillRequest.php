<?php

namespace App\Http\Requests\SupplierBill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'required', 'string', 'max:255',
                Rule::unique('supplier_bills', 'reference_number')->where('company_id', $companyId),
            ],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
