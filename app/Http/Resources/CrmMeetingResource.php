<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'lead_id' => $this->lead_id,
            'deal_id' => $this->deal_id,
            'customer_id' => $this->customer_id,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'booking_page_id' => $this->booking_page_id,
            'booking_notes' => $this->booking_notes,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? ['id' => $this->lead->id, 'name' => $this->lead->name] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'organizer' => $this->whenLoaded('organizer', fn () => $this->organizer ? [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
            ] : null),
            'status' => $this->status,
            'attendees' => $this->whenLoaded('attendees', fn () => $this->attendees->map(fn ($attendee) => [
                'id' => $attendee->id,
                'user' => $attendee->user ? ['id' => $attendee->user->id, 'name' => $attendee->user->name] : null,
                'external_name' => $attendee->external_name,
                'external_email' => $attendee->external_email,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
