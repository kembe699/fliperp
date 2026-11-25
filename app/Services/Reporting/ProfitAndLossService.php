<?php

namespace App\Services\Reporting;

use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Grants\DimensionService;
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

    public function generate(int $companyId, string $from, string $to, ?int $branchId = null, array $dimensions = []): array
    {
        $revenueAccounts = $this->accountBalances($companyId, $from, $to, $branchId, 'revenue', $dimensions);
        $expenseAccounts = $this->accountBalances($companyId, $from, $to, $branchId, 'expense', $dimensions);

        // A not-for-profit company has no cost of sales unless it turns it back
        // on (reporting option show_cost_of_sales): every expense is expenditure.
        $nonprofit = NonprofitPresentation::company($companyId);
        $showCostOfSales = $nonprofit ? (bool) $nonprofit->reportingOption('show_cost_of_sales') : true;
        $cogsIds = $showCostOfSales ? self::cogsAccountIds($companyId) : [];
        $cogsAccounts = $expenseAccounts->filter(fn ($row) => in_array($row['account_id'], $cogsIds, true))->values();
        $operatingExpenseAccounts = $expenseAccounts->reject(fn ($row) => in_array($row['account_id'], $cogsIds, true))->values();

        $revenueTotal = round($revenueAccounts->sum('amount'), 2);
        $cogsTotal = round($cogsAccounts->sum('amount'), 2);
        $operatingExpensesTotal = round($operatingExpenseAccounts->sum('amount'), 2);
        $grossProfit = round($revenueTotal - $cogsTotal, 2);
        $netProfit = round($grossProfit - $operatingExpensesTotal, 2);

        $report = [
            'from' => $from,
            'to' => $to,
            'revenue' => ['total' => $revenueTotal, 'accounts' => $revenueAccounts->all()],
            'cogs' => ['total' => $cogsTotal, 'accounts' => $cogsAccounts->all()],
            'gross_profit' => $grossProfit,
            'operating_expenses' => ['total' => $operatingExpensesTotal, 'accounts' => $operatingExpenseAccounts->all()],
            'net_profit' => $netProfit,
        ];

        // Standard companies get the report above unchanged; a not-for-profit
        // one also gets its labels and heading groups (same figures).
        if ($nonprofit) {
            $report['presentation'] = (new NonprofitPresentation($companyId))->incomeAndExpenses($report, $showCostOfSales);
        }

        return $report;
    }

    /**
     * Cost of sales: the default 5300 plus every account a product category
     * posts its cost of sales to, so a category with its own cost account
     * (say "Cost of Drugs") still lands above gross profit, not among
     * operating expenses.
     *
     * @return array<int, int>
     */
    public static function cogsAccountIds(int $companyId): array
    {
        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where(fn ($query) => $query
                ->whereKey(app(\App\Services\Finance\SystemAccountService::class)->find($companyId, 'cost_of_sales')?->id ?? 0)
                ->orWhereIn('id', Category::withoutGlobalScopes()->where('company_id', $companyId)->whereNotNull('cogs_account_id')->select('cogs_account_id')))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, array{account_id:int,code:string,name:string,amount:float}>
     */
    protected function accountBalances(int $companyId, string $from, string $to, ?int $branchId, string $type, array $dimensions = []): Collection
    {
        $rows = JournalEntryLine::query()
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->tap(fn ($query) => DimensionService::filter($query, $dimensions))
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
