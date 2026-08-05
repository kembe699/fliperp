<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmEmailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'lead_id' => $this->lead_id,
            'deal_id' => $this->deal_id,
            'customer_id' => $this->customer_id,
            'quotation_id' => $this->quotation_id,
            'to_email' => $this->to_email,
            'to_name' => $this->to_name,
            'subject' => $this->subject,
            'body' => $this->body,
            'sent_by' => $this->whenLoaded('sentBy', fn () => $this->sentBy ? [
                'id' => $this->sentBy->id,
                'name' => $this->sentBy->name,
            ] : null),
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
