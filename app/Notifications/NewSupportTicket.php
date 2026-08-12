<?php

namespace App\Notifications;

use App\Models\PlatformTicket;

/**
 * Fired to every Nile Hive platform-staff user when a client raises a new
 * support ticket. See SupportTicketController::store().
 */
class NewSupportTicket extends AppNotification
{
    public function __construct(public PlatformTicket $ticket) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New support ticket',
            'body' => "{$this->ticket->company->name} raised: {$this->ticket->subject}",
            'link' => "/platform-admin/tickets/{$this->ticket->id}",
            'category' => 'support_ticket_new',
        ];
    }
}
