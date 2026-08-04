<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Validation\ValidationException;

/**
 * Simple indirect-method-lite cash flow: net movement through the Cash
 * account only, grouped by the activity (source_module) that moved it. Not a
 * full indirect-method reconciliation against net income.
 *
 * Shape of generate():
 * [
 *   'from' => 'Y-m-d', 'to' => 'Y-m-d',
 *   'categories' => [
 *     ['source_module' => 'sales', 'cash_in' => float, 'cash_out' => float, 'net' => float],
 *     ['source_module' => 'supplier_payments', ...],
 *     ['source_module' => 'customer_payments', ...],
 *     ['source_module' => 'payroll_runs', ...],
 *   ],
 *   'net_cash_movement' => float,
 * ]
 */
class CashFlowService
{
    public const CASH_ACCOUNT_CODE = '1000';

    protected array $categoryLabels = [
        'pos' => 'sales',
        'sales' => 'customer_payments',
        'procurement' => 'supplier_payments',
        'payroll' => 'payroll_runs',
    ];

    public function generate(int $companyId, string $from, string $to, ?int $branchId = null): array
    {
        $cashAccount = ChartOfAccount::query()->where('company_id', $companyId)->where('code', self::CASH_ACCOUNT_CODE)->first();

        if (! $cashAccount) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ['Required account with code '.self::CASH_ACCOUNT_CODE.' is missing from the chart of accounts.'],
            ]);
        }

        $rows = JournalEntryLine::query()
            ->selectRaw('journal_entries.source_module, SUM(journal_entry_lines.debit) as cash_in, SUM(journal_entry_lines.credit) as cash_out')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $cashAccount->id)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->groupBy('journal_entries.source_module')
            ->get()
            ->keyBy('source_module');

        $netCashMovement = 0.0;

        $categories = collect($this->categoryLabels)->map(function (string $label, string $sourceModule) use ($rows, &$netCashMovement) {
            $row = $rows->get($sourceModule);
            $cashIn = round((float) ($row->cash_in ?? 0), 2);
            $cashOut = round((float) ($row->cash_out ?? 0), 2);
            $net = round($cashIn - $cashOut, 2);
            $netCashMovement += $net;

            return [
                'source_module' => $label,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'net' => $net,
            ];
        })->values()->all();

        return [
            'from' => $from,
            'to' => $to,
            'categories' => $categories,
            'net_cash_movement' => round($netCashMovement, 2),
        ];
    }
}
