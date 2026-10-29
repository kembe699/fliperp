<?php

namespace App\Services\Budgeting;

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Grants\DimensionService;

/**
 * A budget line coded to a SOF, project or cost centre is compared with the
 * spending coded to it; an uncoded line with all spending on its account.
 * Filtering by a dimension keeps only the budget lines coded to it.
 */
class BudgetVsActualService
{
    public const LINE_DIMENSIONS = ['grant_id', 'project_id', 'cost_centre_id'];

    public function compare(BudgetPeriod $budgetPeriod, array $dimensions = []): array
    {
        return $budgetPeriod->lines()->with(['account', 'grant:id,code', 'project:id,code,name', 'costCentre:id,code'])
            ->tap(fn ($query) => DimensionService::filter($query, array_intersect_key($dimensions, array_flip(self::LINE_DIMENSIONS)), 'budget_lines'))
            ->get()
            ->map(fn (BudgetLine $line) => $this->compareLine($budgetPeriod, $line))
            ->all();
    }

    protected function compareLine(BudgetPeriod $budgetPeriod, BudgetLine $line): array
    {
        $query = JournalEntryLine::query()
            ->where('account_id', $line->account_id)
            ->tap(fn ($query) => DimensionService::filter($query, $line->only(self::LINE_DIMENSIONS)))
            ->whereHas('journalEntry', function ($query) use ($budgetPeriod, $line) {
                $query->where('company_id', $budgetPeriod->company_id)
                    ->whereIn('status', JournalEntry::LEDGER_STATUSES)
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
            'grant_id' => $line->grant_id,
            'grant_code' => $line->grant?->code,
            'project_id' => $line->project_id,
            'project_code' => $line->project ? ($line->project->code ?: $line->project->name) : null,
            'cost_centre_id' => $line->cost_centre_id,
            'cost_centre_code' => $line->costCentre?->code,
            'budgeted_amount' => $budgeted,
            'actual_amount' => $actual,
            'variance' => round($budgeted - $actual, 2),
            'utilization_percent' => $budgeted > 0 ? round(($actual / $budgeted) * 100, 2) : null,
        ];
    }
}
