<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestApproval;

/** To everyone who can approve the step a payment request is now waiting on. */
class PaymentRequestApprovalNeeded extends PaymentRequestNotification
{
    public function __construct(PaymentRequest $paymentRequest, public PaymentRequestApproval $step)
    {
        parent::__construct($paymentRequest);
    }

    public function toArray(object $notifiable): array
    {
        $pr = $this->paymentRequest;

        return [
            'title' => 'Payment request needs your approval',
            'body' => "{$pr->reference_number}: {$this->amountText()} to {$pr->payeeLabel()}, raised by {$pr->requester?->name}. Step {$this->step->sequence}: {$this->step->label}.",
            'link' => "/payment-requests/{$pr->id}",
            'category' => 'payment_request_approval_needed',
        ];
    }
}
