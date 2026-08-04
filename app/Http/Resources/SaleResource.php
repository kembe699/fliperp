<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'customer_id' => $this->customer_id,
            'cash_drawer_session_id' => $this->cash_drawer_session_id,
            'table_id' => $this->table_id,
            'reference_number' => $this->reference_number,
            'sale_type' => $this->sale_type,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'amount_paid' => (float) $this->amount_paid,
            'balance_due' => round((float) $this->total_amount - (float) $this->amount_paid, 2),
            'served_by' => $this->served_by,
            'sale_date' => $this->sale_date?->toDateString(),
            'journal_entry_id' => $this->journal_entry_id,
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
