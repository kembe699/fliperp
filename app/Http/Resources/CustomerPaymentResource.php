<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'invoice_id' => $this->invoice_id,
            'payment_type_id' => $this->payment_type_id,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'reference_number' => $this->reference_number,
            'received_by' => $this->received_by,
            'journal_entry_id' => $this->journal_entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
