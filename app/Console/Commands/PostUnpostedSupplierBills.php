<?php

namespace App\Console\Commands;

use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Procurement\SupplierBillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Bills entered by hand before bills posted to the ledger have no journal
 * entry, so Accounts Payable never showed what was owed on them (while their
 * payments still debited it). This posts each one as SupplierBillService
 * now does on creation: Dr General Operating Expenses (5200) / Cr Accounts
 * Payable, dated on the bill date. Dry run unless --apply.
 */
class PostUnpostedSupplierBills extends Command
{
    protected $signature = 'finance:post-unposted-supplier-bills {--company= : Only this company id} {--apply : Post them (default is a dry run)}';

    protected $description = 'Post manually entered supplier bills that have no journal entry (Dr expense / Cr Accounts Payable)';

    public function handle(SupplierBillService $supplierBillService): int
    {
        $bills = SupplierBill::withoutGlobalScopes()
            ->whereNull('journal_entry_id')
            ->whereNull('grn_id')
            ->where('total_amount', '>', 0)
            ->when($this->option('company'), fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->orderBy('company_id')->orderBy('bill_date')
            ->get();

        if ($bills->isEmpty()) {
            $this->info('No unposted supplier bills.');

            return self::SUCCESS;
        }

        $this->table(['Company', 'Bill', 'Date', 'Currency', 'Total'], $bills->map(fn ($bill) => [
            $bill->company_id, $bill->reference_number, $bill->bill_date->toDateString(), $bill->currency_code, $bill->total_amount,
        ])->all());

        if (! $this->option('apply')) {
            $this->warn("Dry run: {$bills->count()} bill(s) would be posted. Re-run with --apply to post them.");

            return self::SUCCESS;
        }

        $posted = 0;
        foreach ($bills->groupBy('company_id') as $companyId => $companyBills) {
            // Postings are recorded as made by one of the company's own users.
            $user = User::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('id')->first();
            if (! $user) {
                $this->error("Company {$companyId}: no active user to post as; skipped.");

                continue;
            }
            Auth::setUser($user);

            foreach ($companyBills as $bill) {
                try {
                    $supplierBillService->postBill($bill);
                    $posted++;
                } catch (Throwable $e) {
                    $this->error("{$bill->reference_number}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Posted {$posted} of {$bills->count()} bill(s).");

        return self::SUCCESS;
    }
}
