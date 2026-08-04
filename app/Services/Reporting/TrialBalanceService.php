<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;

/**
 * Shape of generate():
 * [
 *   'from' => 'Y-m-d', 'to' => 'Y-m-d',
 *   'accounts' => [
 *     ['account_id' => int, 'code' => string, 'name' => string, 'type' => string,
 *      'debit' => float, 'credit' => float, 'balance' => float],
 *     ...
 *   ],
 *   'total_debit' => float, 'total_credit' => float,
 * ]
 */
class TrialBalanceService
{
    public function generate(int $companyId, string $from, string $to, ?int $branchId = null): array
    {
        $rows = JournalEntryLine::query()
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $rows->keys())
            ->orderBy('code')
            ->get();

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $lines = $accounts->map(function (ChartOfAccount $account) use ($rows, &$totalDebit, &$totalCredit) {
            $row = $rows->get($account->id);
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);
            $isDebitNormal = in_array($account->type, ['asset', 'expense'], true);
            $balance = round($isDebitNormal ? $debit - $credit : $credit - $debit, 2);

            $totalDebit += $debit;
            $totalCredit += $credit;

            return [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        })->values()->all();

        return [
            'from' => $from,
            'to' => $to,
            'accounts' => $lines,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
        ];
    }
}
