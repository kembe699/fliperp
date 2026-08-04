<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where('company_id', $companyId)],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
