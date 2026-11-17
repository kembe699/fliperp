<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentApprovalRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_amount' => (float) $this->min_amount,
            'max_amount' => $this->max_amount === null ? null : (float) $this->max_amount,
            'is_active' => (bool) $this->is_active,
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($s) => [
                'id' => $s->id,
                'sequence' => $s->sequence,
                'label' => $s->label,
                'role_id' => $s->role_id,
                'role_name' => $s->role?->name,
                'user_id' => $s->user_id,
                'user_name' => $s->user?->name,
            ])->values()),
        ];
    }
}
