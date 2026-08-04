<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

/**
 * Shape of generate():
 * [
 *   'from' => 'Y-m-d', 'to' => 'Y-m-d',
 *   'revenue' => ['total' => float, 'accounts' => [['account_id','code','name','amount'], ...]],
 *   'cogs' => ['total' => float, 'accounts' => [...]],
 *   'gross_profit' => float,
 *   'operating_expenses' => ['total' => float, 'accounts' => [...]],
 *   'net_profit' => float,
 * ]
 */
class ProfitAndLossService
{
    public const COGS_ACCOUNT_CODE = '5300';

    public function generate(int $companyId, string $from, string $to, ?int $branchId = null): array
    {
        $revenueAccounts = $this->accountBalances($companyId, $from, $to, $branchId, 'revenue');
        $expenseAccounts = $this->accountBalances($companyId, $from, $to, $branchId, 'expense');

        $cogsAccounts = $expenseAccounts->filter(fn ($row) => $row['code'] === self::COGS_ACCOUNT_CODE)->values();
        $operatingExpenseAccounts = $expenseAccounts->filter(fn ($row) => $row['code'] !== self::COGS_ACCOUNT_CODE)->values();

        $revenueTotal = round($revenueAccounts->sum('amount'), 2);
        $cogsTotal = round($cogsAccounts->sum('amount'), 2);
        $operatingExpensesTotal = round($operatingExpenseAccounts->sum('amount'), 2);
        $grossProfit = round($revenueTotal - $cogsTotal, 2);
        $netProfit = round($grossProfit - $operatingExpensesTotal, 2);

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => ['total' => $revenueTotal, 'accounts' => $revenueAccounts->all()],
            'cogs' => ['total' => $cogsTotal, 'accounts' => $cogsAccounts->all()],
            'gross_profit' => $grossProfit,
            'operating_expenses' => ['total' => $operatingExpensesTotal, 'accounts' => $operatingExpenseAccounts->all()],
            'net_profit' => $netProfit,
        ];
    }

    /**
     * @return Collection<int, array{account_id:int,code:string,name:string,amount:float}>
     */
    protected function accountBalances(int $companyId, string $from, string $to, ?int $branchId, string $type): Collection
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

        $isDebitNormal = $type === 'expense';

        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->whereIn('id', $rows->keys())
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($rows, $isDebitNormal) {
                $row = $rows->get($account->id);
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;

                return [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'amount' => round($isDebitNormal ? $debit - $credit : $credit - $debit, 2),
                ];
            });
    }
}
