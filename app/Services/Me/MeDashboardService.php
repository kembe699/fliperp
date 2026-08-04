<?php

namespace App\Services\Me;

use App\Models\MeIndicator;
use App\Models\MeProject;

class MeDashboardService
{
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
