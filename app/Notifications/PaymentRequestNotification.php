<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared shape of the payment-request notifications: the usual in-app
 * notification (database + live broadcast, see AppNotification) plus an
 * email to the same person, since approvers may not have FlipERP open.
 */
abstract class PaymentRequestNotification extends AppNotification
{
    public function __construct(public PaymentRequest $paymentRequest) {}

    public function via(object $notifiable): array
    {
        return array_merge(parent::via($notifiable), filled($notifiable->email ?? null) ? ['mail'] : []);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'].' – '.$this->paymentRequest->reference_number)
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($data['body'])
            ->action('Open the payment request', rtrim((string) config('app.frontend_url', config('app.url')), '/').$data['link']);
    }

    protected function amountText(): string
    {
        return $this->paymentRequest->currency_code.' '.number_format((float) $this->paymentRequest->amount, 2);
    }
}
