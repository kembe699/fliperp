<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'supplier_id' => $this->supplier_id,
            'supplier_bill_id' => $this->supplier_bill_id,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'payment_type_id' => $this->payment_type_id,
            'reference_number' => $this->reference_number,
            'paid_by' => $this->paid_by,
            'journal_entry_id' => $this->journal_entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
