<?php

namespace App\Notifications;

use App\Models\CrmMeeting;

class CrmMeetingScheduled extends AppNotification
{
    public function __construct(public CrmMeeting $meeting) {}

    public function toArray(object $notifiable): array
    {
        $subjectName = $this->meeting->deal?->title
            ?? $this->meeting->lead?->name
            ?? $this->meeting->customer?->name
            ?? 'a CRM contact';

        $link = match (true) {
            (bool) $this->meeting->deal_id => "/crm/pipeline?deal={$this->meeting->deal_id}",
            (bool) $this->meeting->lead_id => "/crm/pipeline?lead={$this->meeting->lead_id}",
            (bool) $this->meeting->customer_id => "/customers/{$this->meeting->customer_id}/statement",
            default => '/crm/pipeline',
        };

        return [
            'title' => 'Meeting scheduled',
            'body' => "\"{$this->meeting->title}\" with {$subjectName} on {$this->meeting->scheduled_at->format('M j, Y \a\t g:i A')}.",
            'link' => $link,
            'category' => 'crm_meeting_scheduled',
        ];
    }
}
