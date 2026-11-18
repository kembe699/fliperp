<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Crm\MarketingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CRM > Analytics: email, campaign, lead magnet, booking and lead-source numbers. */
class CrmMarketingReportController extends Controller
{
    public function __invoke(Request $request, MarketingReportService $reports): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $this->success($reports->summary(
            $request->user()->company_id,
            $data['from'] ?? now()->subDays(29)->toDateString(),
            $data['to'] ?? now()->toDateString(),
        ));
    }
}
