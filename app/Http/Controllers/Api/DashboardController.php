<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\DashboardSummaryRequest;
use App\Services\Reporting\DashboardAggregationService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(protected DashboardAggregationService $dashboardAggregationService) {}

    public function summary(DashboardSummaryRequest $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        $data = $this->dashboardAggregationService->summary(
            $request->user()->company_id,
            $request->integer('branch_id') ?: null,
            $from,
            $to,
        );

        return $this->success($data);
    }
}
