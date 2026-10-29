<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Grants\DimensionService;

/**
 * Shape of generate():
 * [
 *   'from' => 'Y-m-d', 'to' => 'Y-m-d',
 *   'account' => ['account_id' => int, 'code' => string, 'name' => string, 'type' => string],
 *   'opening_balance' => float,
 *   'lines' => [
 *     ['date' => 'Y-m-d', 'reference_number' => string, 'description' => ?string,
 *      'debit' => float, 'credit' => float, 'balance' => float,
 *      'source_module' => ?string, 'source_id' => ?int],
 *     ...
 *   ],
 *   'closing_balance' => float,
 * ]
 */
class GeneralLedgerService
{
    public function generate(int $companyId, int $accountId, string $from, string $to, ?int $branchId = null, array $dimensions = []): array
    {
        $account = ChartOfAccount::query()->where('company_id', $companyId)->findOrFail($accountId);
        $isDebitNormal = in_array($account->type, ['asset', 'expense'], true);

        $openingTotals = JournalEntryLine::query()
            ->selectRaw('SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->where('journal_entry_lines.account_id', $accountId)
            ->where('journal_entries.entry_date', '<', $from)
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->tap(fn ($query) => DimensionService::filter($query, $dimensions))
            ->first();

        $openingDebit = (float) ($openingTotals->total_debit ?? 0);
        $openingCredit = (float) ($openingTotals->total_credit ?? 0);
        $runningBalance = round($isDebitNormal ? $openingDebit - $openingCredit : $openingCredit - $openingDebit, 2);
        $openingBalance = $runningBalance;

        $entries = JournalEntryLine::query()
            ->select('journal_entry_lines.*', 'journal_entries.entry_date', 'journal_entries.reference_number', 'journal_entries.source_module', 'journal_entries.source_id', 'journal_entries.currency_code', 'journal_entries.exchange_rate')
            ->with(['grant:id,code', 'dea:id,code', 'project:id,code,name', 'costCentre:id,code'])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->where('journal_entry_lines.account_id', $accountId)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->tap(fn ($query) => DimensionService::filter($query, $dimensions))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->get();

        $lines = $entries->map(function ($line) use (&$runningBalance, $isDebitNormal) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $runningBalance = round($runningBalance + ($isDebitNormal ? $debit - $credit : $credit - $debit), 2);

            return [
                'date' => $line->entry_date,
                'reference_number' => $line->reference_number,
                'description' => $line->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
                'source_module' => $line->source_module,
                'source_id' => $line->source_id,
                // The line as it was transacted: its own currency, rate and
                // amounts (debit/credit above are the base-currency figures).
                'currency_code' => $line->currency_code,
                'exchange_rate' => (float) $line->exchange_rate,
                'transaction_debit' => round((float) $line->transaction_debit, 2),
                'transaction_credit' => round((float) $line->transaction_credit, 2),
                'grant_code' => $line->grant?->code,
                'dea_code' => $line->dea?->code,
                'project_code' => $line->project ? ($line->project->code ?: $line->project->name) : null,
                'cost_centre_code' => $line->costCentre?->code,
            ];
        })->values()->all();

        return [
            'from' => $from,
            'to' => $to,
            'account' => [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'opening_balance' => $openingBalance,
            'lines' => $lines,
            'closing_balance' => $runningBalance,
        ];
    }
}
