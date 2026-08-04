<?php

namespace App\Http\Requests\GoodsReceivedNote;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsReceivedNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'reference_number' => [
                'required', 'string', 'max:255',
                Rule::unique('goods_received_notes', 'reference_number')->where('company_id', $companyId),
            ],
            'received_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['nullable', Rule::in(['good', 'damaged', 'rejected'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $purchaseOrderId = $this->input('purchase_order_id');

            if (! $purchaseOrderId) {
                return;
            }

            $purchaseOrder = PurchaseOrder::find($purchaseOrderId);

            if ($purchaseOrder && ! in_array($purchaseOrder->status, ['approved', 'partially_received'], true)) {
                $validator->errors()->add(
                    'purchase_order_id',
                    'Only approved (or partially received) purchase orders can receive a GRN.'
                );
            }
        });
    }
}
