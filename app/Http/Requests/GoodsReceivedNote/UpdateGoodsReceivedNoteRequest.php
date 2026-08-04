<?php

namespace App\Http\Requests\GoodsReceivedNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoodsReceivedNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $grn = $this->route('goods_received_note');

        return [
            'warehouse_id' => ['sometimes', 'required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['sometimes', 'required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('goods_received_notes', 'reference_number')->where('company_id', $companyId)->ignore($grn),
            ],
            'received_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.condition' => ['nullable', Rule::in(['good', 'damaged', 'rejected'])],
        ];
    }
}
