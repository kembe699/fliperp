<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportsCenterService;
use App\Services\Reporting\Export\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Reports Center: every report in one shape (columns, rows, totals) for one table on the frontend. */
class ReportsCenterController extends Controller
{
    public function __construct(protected ReportsCenterService $reports, protected ReportExportService $exporter) {}

    public function show(Request $request, string $report): JsonResponse
    {
        return $this->success($this->data($request, $report));
    }

    /** The same report as CSV, Excel or PDF (?format=csv|xlsx|pdf), same filters, same figures. */
    public function export(Request $request, string $report): Response
    {
        $format = $request->validate(['format' => ['required', Rule::in(ReportExportService::FORMATS)]])['format'];
        $data = $this->data($request, $report);
        $title = self::TITLES[$report] ?? Str::headline($report);

        return $this->exporter->download($this->exporter->center($data, $title), $format, $request->user(), $request->only(['branch_id', 'status', 'group_by', 'account_type', 'source']));
    }

    /** Titles as the Reports Center sidebar shows them (frontend/src/pages/reports-center/catalog.ts). */
    protected const TITLES = [
        'overview' => 'Business Overview',
        'revenue-accounts' => 'Revenue by Account',
        'revenue' => 'Sales & Revenue',
        'collections' => 'Collections',
        'expense-accounts' => 'Expenses by Account',
        'purchases' => 'Purchases (Supplier Bills)',
        'supplier-payments' => 'Supplier Payments',
        'stock' => 'Stock on Hand & Valuation',
        'stock-movements' => 'Stock Movements',
        'payroll' => 'Payroll Summary',
        'audit' => 'Staff Activity / Audit Log',
        'general-ledger' => 'General Ledger (Transaction Listing)',
    ];

    protected function data(Request $request, string $report): array
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

        return $this->reports->run($report, $request->user()->company_id, $filters) + [
            'report' => $report,
            'from' => $filters['from'],
            'to' => $filters['to'],
        ];
    }
}
