<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'supplier_id' => $this->supplier_id,
            'reference_number' => $this->reference_number,
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            // The documents this order led to: its receipts and the supplier bills raised from them.
            'goods_received_notes' => $this->whenLoaded('goodsReceivedNotes', fn () => $this->goodsReceivedNotes->map(fn ($grn) => [
                'id' => $grn->id, 'reference_number' => $grn->reference_number, 'received_date' => $grn->received_date?->toDateString(), 'status' => $grn->status,
            ])->values()),
            'supplier_bills' => $this->whenLoaded('bills', fn () => $this->bills->map(fn ($bill) => [
                'id' => $bill->id, 'reference_number' => $bill->reference_number, 'bill_date' => $bill->bill_date?->toDateString(),
                'total_amount' => (float) $bill->total_amount, 'amount_paid' => (float) $bill->amount_paid, 'status' => $bill->status,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
