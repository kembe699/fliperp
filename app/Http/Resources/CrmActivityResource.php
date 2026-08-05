<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'lead_id' => $this->lead_id,
            'deal_id' => $this->deal_id,
            'type' => $this->type,
            'subject' => $this->subject,
            'description' => $this->description,
            'logged_by' => $this->whenLoaded('loggedBy', fn () => $this->loggedBy ? [
                'id' => $this->loggedBy->id,
                'name' => $this->loggedBy->name,
            ] : null),
            'activity_date' => $this->activity_date?->toDateString(),
            'status' => $this->status,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->whenLoaded('resolvedBy', fn () => $this->resolvedBy ? [
                'id' => $this->resolvedBy->id,
                'name' => $this->resolvedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
