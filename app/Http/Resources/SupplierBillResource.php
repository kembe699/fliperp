<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\DimensionFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierBillResource extends JsonResource
{
    use DimensionFields;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'supplier_id' => $this->supplier_id,
            'grn_id' => $this->grn_id,
            'purchase_order_id' => $this->purchase_order_id,
            'reference_number' => $this->reference_number,
            'bill_date' => $this->bill_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'amount_paid' => (float) $this->amount_paid,
            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,
            'expense_account_id' => $this->expense_account_id,
            ...$this->dimensionFields(),
            'balance_due' => round((float) $this->total_amount - (float) $this->amount_paid, 2),
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
