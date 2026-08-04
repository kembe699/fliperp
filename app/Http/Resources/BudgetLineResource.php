<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'budget_period_id' => $this->budget_period_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'account_id' => $this->account_id,
            'budgeted_amount' => (float) $this->budgeted_amount,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
