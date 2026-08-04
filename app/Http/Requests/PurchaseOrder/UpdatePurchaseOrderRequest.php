<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $purchaseOrder = $this->route('purchase_order');

        return [
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['sometimes', 'required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['sometimes', 'required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('purchase_orders', 'reference_number')->where('company_id', $companyId)->ignore($purchaseOrder),
            ],
            'order_date' => ['sometimes', 'required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity_ordered' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
