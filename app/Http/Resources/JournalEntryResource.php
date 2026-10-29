<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'reference_number' => $this->reference_number,
            'entry_date' => $this->entry_date?->toDateString(),
            'description' => $this->description,
            'source_module' => $this->source_module,
            'source_id' => $this->source_id,
            'posted_by' => $this->posted_by,
            'status' => $this->status,
            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            // Base-currency totals (what the ledger and every report use)...
            'total_debit' => $this->whenLoaded('lines', fn () => round((float) $this->lines->sum('debit'), 2)),
            'total_credit' => $this->whenLoaded('lines', fn () => round((float) $this->lines->sum('credit'), 2)),
            // ...and the same in the entry's own currency.
            'total_transaction_debit' => $this->whenLoaded('lines', fn () => round((float) $this->lines->sum('transaction_debit'), 2)),
            'total_transaction_credit' => $this->whenLoaded('lines', fn () => round((float) $this->lines->sum('transaction_credit'), 2)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
