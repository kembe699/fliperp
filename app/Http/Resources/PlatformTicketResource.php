<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whether internal notes are present in `replies` is entirely up to the caller —
 * this resource just serializes whatever reply collection it's handed. The
 * client-facing controller filters is_internal_note=true out BEFORE building
 * this resource; the platform-admin controller doesn't. See PlatformTicket
 * ReplyResource for the per-reply shape.
 */
class PlatformTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'raised_by_user_id' => $this->raised_by_user_id,
            'raised_by_name' => $this->whenLoaded('raisedBy', fn () => $this->raisedBy?->name),
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'source' => $this->source,
            'replies' => PlatformTicketReplyResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
