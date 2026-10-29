<?php

namespace App\Services\Me;

use App\Models\BudgetPeriod;
use App\Models\MeIndicator;
use App\Models\MeProject;
use App\Services\Budgeting\BudgetVsActualService;

class MeDashboardService
{
    public function __construct(protected BudgetVsActualService $budgetVsActualService) {}

    public function dashboard(MeProject $project): array
    {
        $indicators = $project->indicators()
            ->with(['results' => fn ($query) => $query->latest('recorded_at')])
            ->get();

        $activities = $project->activities;
        $totalActivities = $activities->count();
        $completedActivities = $activities->where('status', 'completed')->count();

        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'status' => $project->status,
            'indicators' => $indicators->map(fn (MeIndicator $indicator) => $this->indicatorProgress($indicator))->all(),
            'activities_summary' => [
                'total' => $totalActivities,
                'completed' => $completedActivities,
                'completion_percent' => $totalActivities > 0
                    ? round(($completedActivities / $totalActivities) * 100, 2)
                    : null,
            ],
            'finance' => $this->finance($project),
        ];
    }

    /**
     * Money for the project, from the budget period it is linked to: each
     * budget line against posted ledger activity on its account in that
     * period (BudgetVsActualService). Null when no budget period is linked.
     */
    protected function finance(MeProject $project): ?array
    {
        $period = $project->budget_period_id ? BudgetPeriod::find($project->budget_period_id) : null;

        if (! $period) {
            return null;
        }

        $lines = $this->budgetVsActualService->compare($period);
        $budgeted = round(array_sum(array_column($lines, 'budgeted_amount')), 2);
        $actual = round(array_sum(array_column($lines, 'actual_amount')), 2);

        return [
            'budget_period_id' => $period->id,
            'budget_period_name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'budgeted_total' => $budgeted,
            'actual_total' => $actual,
            'variance' => round($budgeted - $actual, 2),
            'utilization_percent' => $budgeted > 0 ? round($actual / $budgeted * 100, 2) : null,
            'lines' => $lines,
        ];
    }

    protected function indicatorProgress(MeIndicator $indicator): array
    {
        $latestResult = $indicator->results->first();
        $baseline = (float) $indicator->baseline_value;
        $target = (float) $indicator->target_value;
        $actual = $latestResult ? (float) $latestResult->actual_value : $baseline;

        $denominator = $target - $baseline;
        $progressPercent = $denominator != 0.0
            ? round((($actual - $baseline) / $denominator) * 100, 2)
            : null;

        return [
            'indicator_id' => $indicator->id,
            'name' => $indicator->name,
            'unit_of_measure' => $indicator->unit_of_measure,
            'baseline_value' => $baseline,
            'target_value' => $target,
            'actual_value' => $actual,
            'progress_percent' => $progressPercent,
            'last_reporting_period' => $latestResult?->reporting_period,
        ];
    }
}
