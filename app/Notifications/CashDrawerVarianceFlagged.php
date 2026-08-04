<?php

namespace App\Notifications;

use App\Models\CashDrawerSession;

class CashDrawerVarianceFlagged extends AppNotification
{
    public function __construct(public CashDrawerSession $session) {}

    public function toArray(object $notifiable): array
    {
        $variance = (float) $this->session->variance;
        $kind = $variance < 0 ? 'shortage' : 'overage';
        $cashierName = $this->session->user->name ?? 'A cashier';

        return [
            'title' => 'Cash drawer '.$kind,
            'body' => "{$cashierName}'s drawer closed with a ".number_format(abs($variance), 2)." {$kind}.",
            'link' => '/reports/shift-report',
            'category' => 'cash_drawer_variance_flagged',
        ];
    }
}
