<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'quotation_id' => ['nullable', Rule::exists('quotations', 'id')->where('company_id', $companyId)],
            'price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('invoices', 'reference_number')->where('company_id', $companyId),
            ],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
