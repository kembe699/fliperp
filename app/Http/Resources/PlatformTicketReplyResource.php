<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformTicketReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'author_id' => $this->author_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'body' => $this->body,
            'is_internal_note' => $this->is_internal_note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
