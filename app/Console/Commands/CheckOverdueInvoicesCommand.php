<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Notify the invoice creator and branch managers about invoices that are newly overdue';

    public function handle(NotificationRecipientResolver $recipientResolver): int
    {
        $invoices = Invoice::overdue()
            ->whereNull('overdue_notified_at')
            ->with('createdBy')
            ->get();

        foreach ($invoices as $invoice) {
            $managers = $recipientResolver->usersWithPermission($invoice->company_id, 'users.view', $invoice->branch_id);

            $recipients = $invoice->createdBy
                ? $managers->push($invoice->createdBy)->unique('id')
                : $managers;

            Notification::send($recipients, new InvoiceOverdue($invoice));

            $invoice->update(['overdue_notified_at' => now()]);
        }

        $this->info("Notified for {$invoices->count()} newly overdue invoice(s).");

        return self::SUCCESS;
    }
}
