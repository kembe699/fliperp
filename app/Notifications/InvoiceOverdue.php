<?php

namespace App\Notifications;

use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceOverdue extends AppNotification
{
    public function __construct(public Invoice $invoice) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Invoice overdue',
            'body' => "Invoice {$this->invoice->reference_number} for {$this->invoice->customer->name} was due on ".
                Carbon::parse($this->invoice->due_date)->format('d M Y').'.',
            'link' => "/invoices/{$this->invoice->id}",
            'category' => 'invoice_overdue',
        ];
    }
}
