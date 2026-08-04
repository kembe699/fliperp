<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;

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
    public function generate(int $companyId, int $accountId, string $from, string $to, ?int $branchId = null): array
    {
        $account = ChartOfAccount::query()->where('company_id', $companyId)->findOrFail($accountId);
        $isDebitNormal = in_array($account->type, ['asset', 'expense'], true);

        $openingTotals = JournalEntryLine::query()
            ->selectRaw('SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $accountId)
            ->where('journal_entries.entry_date', '<', $from)
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->first();

        $openingDebit = (float) ($openingTotals->total_debit ?? 0);
        $openingCredit = (float) ($openingTotals->total_credit ?? 0);
        $runningBalance = round($isDebitNormal ? $openingDebit - $openingCredit : $openingCredit - $openingDebit, 2);
        $openingBalance = $runningBalance;

        $entries = JournalEntryLine::query()
            ->select('journal_entry_lines.*', 'journal_entries.entry_date', 'journal_entries.reference_number', 'journal_entries.source_module', 'journal_entries.source_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $accountId)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
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
