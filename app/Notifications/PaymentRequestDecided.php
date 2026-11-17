<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use App\Models\User;

/** To the requester: their request was fully approved, rejected (with the reason), or paid. */
class PaymentRequestDecided extends PaymentRequestNotification
{
    public function __construct(PaymentRequest $paymentRequest, public string $decision, public User $by, public ?string $comment = null)
    {
        parent::__construct($paymentRequest);
    }

    public function toArray(object $notifiable): array
    {
        $pr = $this->paymentRequest;
        [$title, $body] = match ($this->decision) {
            'approved' => ['Payment request approved', "{$pr->reference_number} ({$this->amountText()} to {$pr->payeeLabel()}) is fully approved and waiting for payment."],
            'rejected' => ['Payment request rejected', "{$this->by->name} rejected {$pr->reference_number}".($this->comment ? ": \"{$this->comment}\"" : '.').' You can change it and submit it again.'],
            default => ['Payment request paid', "{$pr->reference_number} ({$this->amountText()} to {$pr->payeeLabel()}) was paid by {$this->by->name}."],
        };

        return [
            'title' => $title,
            'body' => $body,
            'link' => "/payment-requests/{$pr->id}",
            'category' => "payment_request_{$this->decision}",
        ];
    }
}
