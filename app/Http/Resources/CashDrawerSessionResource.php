<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDrawerSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'opening_float' => (float) $this->opening_float,
            'closing_float' => $this->closing_float !== null ? (float) $this->closing_float : null,
            'expected_closing' => $this->expected_closing !== null ? (float) $this->expected_closing : null,
            'variance' => $this->variance !== null ? (float) $this->variance : null,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
