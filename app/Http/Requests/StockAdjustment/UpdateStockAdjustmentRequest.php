<?php

namespace App\Http\Requests\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $adjustment = $this->route('stock_adjustment');

        return [
            'warehouse_id' => ['sometimes', 'required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('stock_adjustments', 'reference_number')->where('company_id', $companyId)->ignore($adjustment),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.counted_quantity' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
