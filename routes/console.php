<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:check-overdue')->daily();
// Email campaigns go out in batches (no queue worker on the droplet).
Schedule::command('crm:send-campaigns')->everyMinute()->withoutOverlapping(10);
