<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pos\PosReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosReportController extends Controller
{
    public function __construct(protected PosReportService $posReportService) {}

    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->string('date', now()->toDateString())->toString();
        $branchId = $request->integer('branch_id') ?: null;

        return $this->success($this->posReportService->dailySummary($date, $branchId));
    }
}
