<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SupplierBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Accounts receivable (invoices) and accounts payable (supplier bills) as at
 * a date: what is still owed on each document counting only payments made
 * by then, aged by days past its due date, totalled per customer/supplier,
 * and checked against the ledger's control account (1100 / 2000).
 *
 * Balances are in each document's own currency and in base at the
 * document's own rate — the amount the ledger carries it at — so the base
 * total reconciles with the control account. A non-zero `difference` means
 * something was posted to the control account outside invoices/bills, or a
 * document has no posting (e.g. bills entered before bills posted; see the
 * finance:post-unposted-supplier-bills command).
 *
 * Buckets are by days past due: 0–30 (including not yet due), 31–60, 61–90
 * and 90+; `not_yet_due` is shown alongside for information.
 */
class AgingReportService
{
    public const RECEIVABLE_ACCOUNT_CODE = '1100';

    public const PAYABLE_ACCOUNT_CODE = '2000';

    protected const BUCKETS = ['d0_30', 'd31_60', 'd61_90', 'd90_plus'];

    public function receivables(int $companyId, string $asOf): array
    {
        $documents = Invoice::withoutGlobalScopes()
            ->with('customer:id,name')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereDate('invoice_date', '<=', $asOf)
            ->withSum(['payments as paid_as_of' => fn ($query) => $query->whereDate('payment_date', '<=', $asOf)], 'amount')
            ->get()
            ->map(fn (Invoice $invoice) => $this->documentRow(
                $invoice->id, $invoice->reference_number, $invoice->customer_id, $invoice->customer?->name,
                $invoice->invoice_date, $invoice->due_date, $invoice->currency_code, (float) $invoice->exchange_rate,
                (float) $invoice->total_amount, (float) $invoice->paid_as_of, $asOf,
            ));

        return $this->build($companyId, $asOf, $documents, 'customer', self::RECEIVABLE_ACCOUNT_CODE, debitNormal: true);
    }

    public function payables(int $companyId, string $asOf): array
    {
        $documents = SupplierBill::withoutGlobalScopes()
            ->with('supplier:id,name')
            ->where('company_id', $companyId)
            ->whereDate('bill_date', '<=', $asOf)
            ->withSum(['payments as paid_as_of' => fn ($query) => $query->whereDate('payment_date', '<=', $asOf)], 'amount')
            ->get()
            ->map(fn (SupplierBill $bill) => $this->documentRow(
                $bill->id, $bill->reference_number, $bill->supplier_id, $bill->supplier?->name,
                $bill->bill_date, $bill->due_date, $bill->currency_code, (float) $bill->exchange_rate,
                (float) $bill->total_amount, (float) $bill->paid_as_of, $asOf,
            ));

        return $this->build($companyId, $asOf, $documents, 'supplier', self::PAYABLE_ACCOUNT_CODE, debitNormal: false);
    }

    protected function documentRow(
        int $id, string $reference, int $partyId, ?string $partyName, $date, $dueDate,
        ?string $currency, float $rate, float $total, float $paid, string $asOf,
    ): array {
        $balance = round($total - $paid, 2);
        $rate = $rate > 0 ? $rate : 1.0;
        $daysOverdue = (int) Carbon::parse($dueDate)->startOfDay()->diffInDays(Carbon::parse($asOf)->startOfDay(), false);

        return [
            'id' => $id,
            'reference_number' => $reference,
            'party_id' => $partyId,
            'party_name' => $partyName ?? '—',
            'date' => Carbon::parse($date)->toDateString(),
            'due_date' => Carbon::parse($dueDate)->toDateString(),
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'total' => round($total, 2),
            'paid' => round($paid, 2),
            'balance' => $balance,
            'balance_base' => round($balance / $rate, 2),
            'days_overdue' => max(0, $daysOverdue),
            'bucket' => match (true) {
                $daysOverdue <= 30 => 'd0_30',
                $daysOverdue <= 60 => 'd31_60',
                $daysOverdue <= 90 => 'd61_90',
                default => 'd90_plus',
            },
            'not_yet_due' => $daysOverdue <= 0,
        ];
    }

    protected function build(int $companyId, string $asOf, Collection $documents, string $partyKey, string $controlCode, bool $debitNormal): array
    {
        $open = $documents->filter(fn (array $row) => $row['balance'] > 0.004)->sortBy('due_date')->values();

        $emptyBuckets = array_fill_keys(self::BUCKETS, 0.0);
        $totals = ['outstanding' => 0.0, 'overdue' => 0.0, 'not_yet_due' => 0.0] + $emptyBuckets;
        $parties = [];

        foreach ($open as $row) {
            $amount = $row['balance_base'];
            $totals['outstanding'] += $amount;
            $totals[$row['bucket']] += $amount;
            $totals[$row['not_yet_due'] ? 'not_yet_due' : 'overdue'] += $amount;

            $party = $parties[$row['party_id']] ??= [
                "{$partyKey}_id" => $row['party_id'], 'name' => $row['party_name'],
                'documents' => 0, 'outstanding' => 0.0, 'overdue' => 0.0, 'oldest_days_overdue' => 0,
            ] + $emptyBuckets;
            $party['documents']++;
            $party['outstanding'] += $amount;
            $party[$row['bucket']] += $amount;
            if (! $row['not_yet_due']) {
                $party['overdue'] += $amount;
            }
            $party['oldest_days_overdue'] = max($party['oldest_days_overdue'], $row['days_overdue']);
            $parties[$row['party_id']] = $party;
        }

        $round = fn (array $values) => array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $values);
        $controlBalance = $this->controlBalance($companyId, $controlCode, $asOf, $debitNormal);
        $outstanding = round($totals['outstanding'], 2);

        return [
            'as_of' => $asOf,
            'totals' => $round($totals),
            'control_account' => [
                'code' => $controlCode,
                'name' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $controlCode)->value('name'),
                'balance' => $controlBalance,
            ],
            'difference' => round($controlBalance - $outstanding, 2),
            "{$partyKey}s" => collect($parties)->map($round)->sortByDesc('outstanding')->values()->all(),
            'documents' => $open->all(),
        ];
    }

    protected function controlBalance(int $companyId, string $code, string $asOf, bool $debitNormal): float
    {
        $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            return 0.0;
        }

        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->where('journal_entry_lines.account_id', $account->id)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as debit, COALESCE(SUM(journal_entry_lines.credit), 0) as credit')
            ->first();

        $balance = $debitNormal ? (float) $totals->debit - (float) $totals->credit : (float) $totals->credit - (float) $totals->debit;

        return round($balance, 2);
    }
}
