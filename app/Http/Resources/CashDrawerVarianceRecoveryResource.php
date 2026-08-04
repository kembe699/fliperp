<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDrawerVarianceRecoveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'cash_drawer_session_id' => $this->cash_drawer_session_id,
            'amount' => (float) $this->amount,
            'notes' => $this->notes,
            'received_by' => $this->received_by,
            'journal_entry_id' => $this->journal_entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
