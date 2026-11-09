<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportsCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Reports Center: every report in one shape (columns, rows, totals) for one table on the frontend. */
class ReportsCenterController extends Controller
{
    public function __construct(protected ReportsCenterService $reports) {}

    public function show(Request $request, string $report): JsonResponse
    {
        abort_unless(in_array($report, ReportsCenterService::REPORTS, true), 404, 'Unknown report');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $request->user()->company_id)],
            'group_by' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', Rule::in(['all', 'pos', 'invoice'])],
            'status' => ['nullable', 'string', 'max:30'],
            'account_type' => ['nullable', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
        ]);
        $filters['from'] ??= now()->startOfMonth()->toDateString();
        $filters['to'] ??= now()->toDateString();
        $filters['branch_id'] = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        return $this->success($this->reports->run($report, $request->user()->company_id, $filters) + [
            'report' => $report,
            'from' => $filters['from'],
            'to' => $filters['to'],
        ]);
    }
}
