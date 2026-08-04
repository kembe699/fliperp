<?php

namespace App\Http\Requests\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'from_warehouse_id' => ['required', 'different:to_warehouse_id', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'to_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'required', 'string', 'max:255',
                Rule::unique('stock_transfers', 'reference_number')->where('company_id', $companyId),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
