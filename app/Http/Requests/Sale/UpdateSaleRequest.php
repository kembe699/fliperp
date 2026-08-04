<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
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
            'table_id' => ['nullable', Rule::exists('restaurant_tables', 'id')->where('company_id', $companyId)],
            'sale_type' => ['nullable', Rule::in(['pos', 'dine_in', 'takeaway', 'delivery'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_date' => ['nullable', 'date'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
