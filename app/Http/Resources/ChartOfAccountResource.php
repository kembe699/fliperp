<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'opening_balance' => (float) ($this->opening_balance ?? 0),
            'opening_balance_date' => $this->opening_balance_date?->toDateString(),
            'opening_balance_entry_id' => $this->opening_balance_entry_id,
            // The ledger balance in the account's own direction (ChartOfAccountService::attachBalances).
            'balance' => $this->when($this->resource->getAttribute('balance') !== null, fn () => (float) $this->resource->getAttribute('balance')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
