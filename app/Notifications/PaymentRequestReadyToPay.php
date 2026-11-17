<?php

namespace App\Notifications;

/** To Finance (everyone who can pay): a request is fully approved. */
class PaymentRequestReadyToPay extends PaymentRequestNotification
{
    public function toArray(object $notifiable): array
    {
        $pr = $this->paymentRequest;

        return [
            'title' => 'Payment request ready to pay',
            'body' => "{$pr->reference_number}: {$this->amountText()} to {$pr->payeeLabel()} is fully approved.",
            'link' => "/payment-requests/{$pr->id}",
            'category' => 'payment_request_ready_to_pay',
        ];
    }
}
