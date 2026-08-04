<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'allow_negative_stock' => ['nullable', 'boolean'],
        ];
    }
}
