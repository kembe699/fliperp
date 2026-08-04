<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'table_id' => ['nullable', Rule::exists('restaurant_tables', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('sales', 'reference_number')->where('company_id', $companyId),
            ],
            'sale_type' => ['nullable', Rule::in(['pos', 'dine_in', 'takeaway', 'delivery'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
