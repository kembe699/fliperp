<?php

namespace App\Services\Budgeting;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\JournalEntryLine;

class BudgetVsActualService
{
    public function compare(BudgetPeriod $budgetPeriod): array
    {
        return $budgetPeriod->lines()->with('account')
            ->get()
            ->map(fn (BudgetLine $line) => $this->compareLine($budgetPeriod, $line))
            ->all();
    }

    protected function compareLine(BudgetPeriod $budgetPeriod, BudgetLine $line): array
    {
        $query = JournalEntryLine::query()
            ->where('account_id', $line->account_id)
            ->whereHas('journalEntry', function ($query) use ($budgetPeriod, $line) {
                $query->where('company_id', $budgetPeriod->company_id)
                    ->where('status', 'posted')
                    ->whereBetween('entry_date', [$budgetPeriod->start_date, $budgetPeriod->end_date]);

                if ($line->branch_id) {
                    $query->where('branch_id', $line->branch_id);
                }
            });

        $totalDebit = (float) $query->sum('debit');
        $totalCredit = (float) $query->sum('credit');

        $isNormalDebitBalance = in_array($line->account->type, ['asset', 'expense'], true);
        $actual = round($isNormalDebitBalance ? $totalDebit - $totalCredit : $totalCredit - $totalDebit, 2);
        $budgeted = (float) $line->budgeted_amount;

        return [
            'budget_line_id' => $line->id,
            'account_id' => $line->account_id,
            'account_code' => $line->account->code,
            'account_name' => $line->account->name,
            'budgeted_amount' => $budgeted,
            'actual_amount' => $actual,
            'variance' => round($budgeted - $actual, 2),
            'utilization_percent' => $budgeted > 0 ? round(($actual / $budgeted) * 100, 2) : null,
        ];
    }
}
