<?php

namespace App\Notifications;

use App\Models\PayrollRun;

class PayrollRunProcessed extends AppNotification
{
    public function __construct(public PayrollRun $payrollRun) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payroll run processed',
            'body' => "Payroll for {$this->payrollRun->period_start} to {$this->payrollRun->period_end} was processed.",
            'link' => "/payroll-runs/{$this->payrollRun->id}",
            'category' => 'payroll_run_processed',
        ];
    }
}
