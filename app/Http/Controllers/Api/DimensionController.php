<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CostCentre;
use App\Models\Grant;
use App\Models\MeProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The coding choices for the line pickers on every form and report filter:
 * SOFs (with their DEAs), projects and cost centres. Open to any signed-in
 * user of the company — whoever enters a bill or journal codes it, not only
 * those who manage grants. `all=1` includes closed/inactive ones (for
 * report filters on past periods).
 */
class DimensionController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $all = $request->boolean('all');

        $grants = Grant::query()
            ->when(! $all, fn ($query) => $query->where('status', 'active'))
            ->with(['donor:id,name', 'deas' => fn ($query) => $query->when(! $all, fn ($inner) => $inner->where('is_active', true))->orderBy('code')])
            ->orderBy('code')
            ->get();

        return $this->success([
            'grants' => $grants->map(fn (Grant $grant) => [
                'id' => $grant->id,
                'code' => $grant->code,
                'name' => $grant->name,
                'donor_name' => $grant->donor?->name,
                'currency_code' => $grant->currency_code,
                'start_date' => $grant->start_date->toDateString(),
                'end_date' => $grant->end_date->toDateString(),
                'status' => $grant->status,
                'deas' => $grant->deas->map(fn ($dea) => ['id' => $dea->id, 'code' => $dea->code, 'name' => $dea->name])->values(),
            ])->values(),
            'projects' => MeProject::query()
                ->when(! $all, fn ($query) => $query->whereIn('status', ['planned', 'ongoing']))
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn ($project) => ['id' => $project->id, 'code' => $project->code, 'name' => $project->name])
                ->values(),
            'cost_centres' => CostCentre::query()
                ->when(! $all, fn ($query) => $query->where('is_active', true))
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }
}
