<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantBudgetLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grant_id' => $this->grant_id,
            'account_id' => $this->account_id,
            'account_code' => $this->whenLoaded('account', fn () => $this->account->code),
            'account_name' => $this->whenLoaded('account', fn () => $this->account->name),
            'dea_id' => $this->dea_id,
            'dea_code' => $this->whenLoaded('dea', fn () => $this->dea?->code),
            'budgeted_amount' => (float) $this->budgeted_amount,
        ];
    }
}
