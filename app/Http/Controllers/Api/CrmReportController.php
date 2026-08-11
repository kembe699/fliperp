<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmReport\CrmReportSummaryRequest;
use App\Services\Crm\CrmReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmReportController extends Controller
{
    public function __construct(protected CrmReportService $crmReportService) {}

    public function summary(CrmReportSummaryRequest $request): JsonResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $data = $this->crmReportService->summary(
            $request->user()->company_id,
            $request->integer('branch_id') ?: null,
            $from,
            $to,
        );

        return $this->success($data);
    }

    public function staff(Request $request): JsonResponse
    {
        $data = $this->crmReportService->staff($request->user()->company_id);

        return $this->success($data);
    }

    public function trends(Request $request): JsonResponse
    {
        $data = $this->crmReportService->trends(
            $request->user()->company_id,
            $request->integer('branch_id') ?: null,
            $request->integer('months') ?: 6,
        );

        return $this->success($data);
    }
}
