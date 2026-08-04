<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

/**
 * Shape of generate():
 * [
 *   'as_of' => 'Y-m-d',
 *   'assets' => ['total' => float, 'accounts' => [['account_id','code','name','amount'], ...]],
 *   'liabilities' => ['total' => float, 'accounts' => [...]],
 *   'equity' => ['total' => float, 'accounts' => [...], 'net_income' => float],
 *   'total_liabilities_and_equity' => float,
 * ]
 *
 * The books in this system are never formally closed to an equity account, so
 * "net_income" (accumulated revenue - expenses up to as_of) is surfaced as a
 * computed equity line — this is what keeps assets == liabilities + equity.
 */
class BalanceSheetService
{
    protected const EPOCH = '1900-01-01';

    public function generate(int $companyId, string $asOf, ?int $branchId = null): array
    {
        $assetAccounts = $this->accountBalances($companyId, $asOf, $branchId, 'asset');
        $liabilityAccounts = $this->accountBalances($companyId, $asOf, $branchId, 'liability');
        $equityAccounts = $this->accountBalances($companyId, $asOf, $branchId, 'equity');

        $assetsTotal = round($assetAccounts->sum('amount'), 2);
        $liabilitiesTotal = round($liabilityAccounts->sum('amount'), 2);
        $equityAccountsTotal = round($equityAccounts->sum('amount'), 2);

        $revenueTotal = round($this->accountBalances($companyId, $asOf, $branchId, 'revenue')->sum('amount'), 2);
        $expenseTotal = round($this->accountBalances($companyId, $asOf, $branchId, 'expense')->sum('amount'), 2);
        $netIncome = round($revenueTotal - $expenseTotal, 2);

        $equityTotal = round($equityAccountsTotal + $netIncome, 2);

        return [
            'as_of' => $asOf,
            'assets' => ['total' => $assetsTotal, 'accounts' => $assetAccounts->all()],
            'liabilities' => ['total' => $liabilitiesTotal, 'accounts' => $liabilityAccounts->all()],
            'equity' => ['total' => $equityTotal, 'accounts' => $equityAccounts->all(), 'net_income' => $netIncome],
            'total_liabilities_and_equity' => round($liabilitiesTotal + $equityTotal, 2),
        ];
    }

    /**
     * @return Collection<int, array{account_id:int,code:string,name:string,amount:float}>
     */
    protected function accountBalances(int $companyId, string $asOf, ?int $branchId, string $type): Collection
    {
        $rows = JournalEntryLine::query()
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [self::EPOCH, $asOf])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $isDebitNormal = in_array($type, ['asset', 'expense'], true);

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
