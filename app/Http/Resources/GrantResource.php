<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donor_id' => $this->donor_id,
            'donor_name' => $this->whenLoaded('donor', fn () => $this->donor?->name),
            'code' => $this->code,
            'name' => $this->name,
            'currency_code' => $this->currency_code,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget_amount' => (float) $this->budget_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'deas' => GrantDeaResource::collection($this->whenLoaded('deas')),
            'budget_lines' => GrantBudgetLineResource::collection($this->whenLoaded('budgetLines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
