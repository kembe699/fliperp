<?php

namespace App\Notifications;

use App\Models\PlatformTicket;

/**
 * Fired to the user who originally raised a ticket when platform staff post a
 * client-visible reply (never for an internal note — see
 * PlatformTicketController::reply()).
 */
class SupportTicketReplied extends AppNotification
{
    public function __construct(public PlatformTicket $ticket) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Support ticket update',
            'body' => "New reply on your ticket: {$this->ticket->subject}",
            'link' => "/support/tickets/{$this->ticket->id}",
            'category' => 'support_ticket_reply',
        ];
    }
}
