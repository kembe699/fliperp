<?php

namespace App\Http\Requests\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $transfer = $this->route('stock_transfer');

        return [
            'from_warehouse_id' => ['sometimes', 'required', 'different:to_warehouse_id', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'to_warehouse_id' => ['sometimes', 'required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('stock_transfers', 'reference_number')->where('company_id', $companyId)->ignore($transfer),
            ],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
        ];
    }
}
