<?php

namespace App\Services\Procurement;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\SupplierBill;
use App\Services\Finance\ExchangeRateService;
use App\Services\Finance\JournalEntryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A bill entered by hand posts Dr expense (expense_account_id, default 5200)
 * / Cr Accounts Payable for its total, in the bill's currency and rate, so
 * payables in the ledger always match the bills owed. Bills raised from a
 * goods-received note are posted by GrnService (Dr Inventory) instead.
 */
class SupplierBillService
{
    public const ACCOUNTS_PAYABLE_ACCOUNT_CODE = '2000';

    public const DEFAULT_EXPENSE_ACCOUNT_CODE = '5200';

    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected JournalEntryService $journalEntryService,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return SupplierBill::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('bill_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('bill_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner
                    ->where('reference_number', 'ilike', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'ilike', "%{$search}%"))
            ))
            ->latest('bill_date')
            ->paginate($perPage);
    }

    public function create(array $data): SupplierBill
    {
        return DB::transaction(fn () => $this->createBill($data));
    }

    protected function createBill(array $data): SupplierBill
    {
        $subtotal = (float) $data['subtotal'];
        $taxAmount = (float) ($data['tax_amount'] ?? 0);
        [$currency, $rate] = $this->exchangeRateService->resolve(
            Auth::user()->company_id, $data['currency_code'] ?? null, $data['bill_date'], $data['exchange_rate'] ?? null,
        );

        $bill = SupplierBill::create([
            'supplier_id' => $data['supplier_id'],
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'reference_number' => $data['reference_number'],
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
            'status' => 'unpaid',
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'expense_account_id' => $data['expense_account_id'] ?? $this->defaultExpenseAccountId(Auth::user()->company_id),
        ]);

        $this->postBill($bill);

        return $this->refreshStatus($bill);
    }

    public function update(SupplierBill $bill, array $data): SupplierBill
    {
        if ((float) $bill->amount_paid > 0) {
            throw ValidationException::withMessages([
                'bill' => ['Cannot edit a bill that already has payments applied.'],
            ]);
        }

        $subtotal = array_key_exists('subtotal', $data) ? (float) $data['subtotal'] : (float) $bill->subtotal;
        $taxAmount = array_key_exists('tax_amount', $data) ? (float) $data['tax_amount'] : (float) $bill->tax_amount;

        $payload = collect($data)->except(['subtotal', 'tax_amount', 'currency_code', 'exchange_rate'])->toArray();

        if (array_key_exists('currency_code', $data) || array_key_exists('exchange_rate', $data) || isset($data['bill_date'])) {
            $keepsManualRate = ! array_key_exists('currency_code', $data) && ! isset($data['bill_date']);
            [$payload['currency_code'], $payload['exchange_rate']] = $this->exchangeRateService->resolve(
                $bill->company_id,
                $data['currency_code'] ?? $bill->currency_code,
                $data['bill_date'] ?? $bill->bill_date->toDateString(),
                $data['exchange_rate'] ?? ($keepsManualRate ? $bill->exchange_rate : null),
            );
        }
        $payload['subtotal'] = $subtotal;
        $payload['tax_amount'] = $taxAmount;
        $payload['total_amount'] = round($subtotal + $taxAmount, 2);

        return DB::transaction(function () use ($bill, $payload) {
            $bill->update($payload);

            // A manual bill's posting follows its amounts: the old entry is
            // reversed and the bill posted again as it now stands.
            if (! $bill->grn_id && $bill->wasChanged(['total_amount', 'currency_code', 'exchange_rate', 'bill_date', 'expense_account_id'])) {
                $this->reversePosting($bill);
                $this->postBill($bill);
            }

            return $this->refreshStatus($bill);
        });
    }

    public function delete(SupplierBill $bill): void
    {
        if ((float) $bill->amount_paid > 0 || ($bill->grn_id && $bill->journal_entry_id)) {
            throw ValidationException::withMessages([
                'bill' => ['Cannot delete a bill that has payments, or one raised from a goods-received note.'],
            ]);
        }

        DB::transaction(function () use ($bill) {
            $this->reversePosting($bill);
            $bill->delete();
        });
    }

    /**
     * Dr expense / Cr Accounts Payable for the bill total. Skipped for
     * goods-received bills (posted to Inventory by GrnService) and for a zero
     * total. Public so bills from before bills posted can be caught up
     * (see the finance:post-unposted-supplier-bills command).
     */
    public function postBill(SupplierBill $bill): ?JournalEntry
    {
        $total = round((float) $bill->total_amount, 2);

        if ($bill->grn_id || $total <= 0) {
            return null;
        }

        $expenseAccountId = $bill->expense_account_id ?? $this->defaultExpenseAccountId($bill->company_id);
        $payableAccount = $this->resolveAccount($bill->company_id, self::ACCOUNTS_PAYABLE_ACCOUNT_CODE);

        $entry = $this->journalEntryService->postModuleEntry([
            'company_id' => $bill->company_id,
            'reference_number' => 'BILL-JE-'.$bill->reference_number.'-'.Str::upper(Str::random(4)),
            'entry_date' => $bill->bill_date->toDateString(),
            'description' => "Supplier bill {$bill->reference_number}",
            'source_module' => 'procurement',
            'source_id' => $bill->id,
            'currency_code' => $bill->currency_code,
            'exchange_rate' => $bill->exchange_rate,
            'lines' => [
                ['account_id' => $expenseAccountId, 'debit' => $total, 'credit' => 0, 'description' => 'Supplier bill expense'],
                ['account_id' => $payableAccount->id, 'debit' => 0, 'credit' => $total, 'description' => 'Amount owed to supplier'],
            ],
        ]);

        $bill->update(['journal_entry_id' => $entry->id, 'expense_account_id' => $expenseAccountId]);

        return $entry;
    }

    protected function reversePosting(SupplierBill $bill): void
    {
        $entry = $bill->journal_entry_id ? JournalEntry::find($bill->journal_entry_id) : null;

        if ($entry && $entry->status === 'posted') {
            $this->journalEntryService->reverse($entry);
        }

        $bill->update(['journal_entry_id' => null]);
    }

    protected function defaultExpenseAccountId(int $companyId): int
    {
        return $this->resolveAccount($companyId, self::DEFAULT_EXPENSE_ACCOUNT_CODE)->id;
    }

    protected function resolveAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."],
            ]);
        }

        return $account;
    }

    public function refreshStatus(SupplierBill $bill): SupplierBill
    {
        $status = $this->determineStatus($bill);

        if ($status !== $bill->status) {
            $bill->update(['status' => $status]);
        }

        return $bill;
    }

    protected function determineStatus(SupplierBill $bill): string
    {
        $totalAmount = (float) $bill->total_amount;
        $amountPaid = (float) $bill->amount_paid;

        if ($totalAmount > 0 && $amountPaid >= $totalAmount) {
            return 'paid';
        }

        if (Carbon::parse($bill->due_date)->isBefore(Carbon::today())) {
            return 'overdue';
        }

        return $amountPaid > 0 ? 'partially_paid' : 'unpaid';
    }
}
