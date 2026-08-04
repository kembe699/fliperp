<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared wiring for every in-app notification: stored to the database AND
 * pushed live over the recipient's private-user.{id} channel (see
 * User::receivesBroadcastNotificationsOn()). Deliberately NOT queued — this
 * app's local dev setup only runs php artisan serve + reverb:start + npm
 * run dev, no queue worker, so a queued notification would silently never
 * broadcast. If a queue worker becomes part of the standard setup later,
 * add `implements ShouldQueue` here and nowhere else.
 *
 * Every subclass just needs a constructor and toArray() — title/body/link/
 * category is the whole contract, kept in one place so the frontend can
 * render any notification type generically.
 */
abstract class AppNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * One event name for every notification type — the frontend keys off
     * the "category" field in the payload (see toArray() on each subclass)
     * to pick an icon and decide whether it's toast-worthy, rather than
     * subscribing to 8 differently-named broadcast events.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    abstract public function toArray(object $notifiable): array;
}
