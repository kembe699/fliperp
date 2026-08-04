<?php

namespace App\Notifications;

use App\Models\Sale;

class SaleVoided extends AppNotification
{
    public function __construct(public Sale $sale) {}

    public function toArray(object $notifiable): array
    {
        $cashierName = $this->sale->servedBy?->name ?? 'A cashier';

        return [
            'title' => 'Sale voided',
            'body' => "{$cashierName} voided sale {$this->sale->reference_number} for ".number_format((float) $this->sale->total_amount, 2).'.',
            'link' => '/receipts',
            'category' => 'sale_voided',
        ];
    }
}
