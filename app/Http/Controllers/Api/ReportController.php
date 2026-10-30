<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\BalanceSheetRequest;
use App\Http\Requests\Report\CashFlowRequest;
use App\Http\Requests\Report\GeneralLedgerRequest;
use App\Http\Requests\Report\ProfitAndLossRequest;
use App\Http\Requests\Report\TrialBalanceRequest;
use App\Http\Resources\ReportSnapshotResource;
use App\Services\Grants\DimensionService;
use App\Services\Reporting\AgingReportService;
use App\Services\Reporting\BalanceSheetService;
use App\Services\Reporting\CashFlowService;
use App\Services\Reporting\GeneralLedgerService;
use App\Services\Reporting\ProfitAndLossService;
use App\Services\Reporting\ReportCurrencyConverter;
use App\Services\Reporting\ReportSnapshotService;
use App\Services\Reporting\TrialBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    protected const SNAPSHOT_TYPES = ['trial-balance', 'profit-and-loss', 'balance-sheet', 'general-ledger', 'cash-flow'];

    public function __construct(
        protected TrialBalanceService $trialBalanceService,
        protected ProfitAndLossService $profitAndLossService,
        protected BalanceSheetService $balanceSheetService,
        protected GeneralLedgerService $generalLedgerService,
        protected CashFlowService $cashFlowService,
        protected ReportSnapshotService $reportSnapshotService,
        protected ReportCurrencyConverter $currencyConverter,
        protected AgingReportService $agingReportService,
    ) {}

    /** Accounts receivable as at a date: open invoices aged, per customer, against control account 1100. */
    public function receivables(Request $request): JsonResponse
    {
        $asOf = $request->validate(['as_of' => ['nullable', 'date']])['as_of'] ?? now()->toDateString();

        return $this->success($this->agingReportService->receivables($request->user()->company_id, $asOf));
    }

    /** Accounts payable as at a date: open supplier bills aged, per supplier, against control account 2000. */
    public function payables(Request $request): JsonResponse
    {
        $asOf = $request->validate(['as_of' => ['nullable', 'date']])['as_of'] ?? now()->toDateString();

        return $this->success($this->agingReportService->payables($request->user()->company_id, $asOf));
    }

    public function trialBalance(TrialBalanceRequest $request): JsonResponse
    {
        $data = $this->trialBalanceService->generate(
            $request->user()->company_id,
            $request->string('from')->toString(),
            $request->string('to')->toString(),
            $request->integer('branch_id') ?: null,
            $this->dimensionFilters($request),
        );

        $data = $this->currencyConverter->convert(
            $data,
            $request->user()->company_id,
            $request->input('currency'),
            $request->string('to')->toString(),
        );

        return $this->success($data);
    }

    public function profitAndLoss(ProfitAndLossRequest $request): JsonResponse
    {
        $data = $this->profitAndLossService->generate(
            $request->user()->company_id,
            $request->string('from')->toString(),
            $request->string('to')->toString(),
            $request->integer('branch_id') ?: null,
            $this->dimensionFilters($request),
        );

        $data = $this->currencyConverter->convert(
            $data,
            $request->user()->company_id,
            $request->input('currency'),
            $request->string('to')->toString(),
        );

        return $this->success($data);
    }

    public function balanceSheet(BalanceSheetRequest $request): JsonResponse
    {
        $data = $this->balanceSheetService->generate(
            $request->user()->company_id,
            $request->string('as_of')->toString(),
            $request->integer('branch_id') ?: null,
        );

        $data = $this->currencyConverter->convert(
            $data,
            $request->user()->company_id,
            $request->input('currency'),
            $request->string('as_of')->toString(),
        );

        return $this->success($data);
    }

    public function generalLedger(GeneralLedgerRequest $request): JsonResponse
    {
        $data = $this->generalLedgerService->generate(
            $request->user()->company_id,
            $request->integer('account_id'),
            $request->string('from')->toString(),
            $request->string('to')->toString(),
            $request->integer('branch_id') ?: null,
            $this->dimensionFilters($request),
        );

        $data = $this->currencyConverter->convert(
            $data,
            $request->user()->company_id,
            $request->input('currency'),
            $request->string('to')->toString(),
        );

        return $this->success($data);
    }

    public function cashFlow(CashFlowRequest $request): JsonResponse
    {
        $data = $this->cashFlowService->generate(
            $request->user()->company_id,
            $request->string('from')->toString(),
            $request->string('to')->toString(),
            $request->integer('branch_id') ?: null,
        );

        $data = $this->currencyConverter->convert(
            $data,
            $request->user()->company_id,
            $request->input('currency'),
            $request->string('to')->toString(),
        );

        return $this->success($data);
    }

    public function snapshot(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::SNAPSHOT_TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => ['Unknown report type. Expected one of: '.implode(', ', self::SNAPSHOT_TYPES).'.'],
            ]);
        }

        $companyId = $request->user()->company_id;
        $branchId = $request->integer('branch_id') ?: null;

        [$periodStart, $periodEnd, $data] = match ($type) {
            'trial-balance' => $this->snapshotRange($request, fn ($from, $to) => $this->trialBalanceService->generate($companyId, $from, $to, $branchId)),
            'profit-and-loss' => $this->snapshotRange($request, fn ($from, $to) => $this->profitAndLossService->generate($companyId, $from, $to, $branchId)),
            'cash-flow' => $this->snapshotRange($request, fn ($from, $to) => $this->cashFlowService->generate($companyId, $from, $to, $branchId)),
            'general-ledger' => $this->snapshotGeneralLedger($request, $companyId, $branchId),
            'balance-sheet' => $this->snapshotAsOf($request, fn ($asOf) => $this->balanceSheetService->generate($companyId, $asOf, $branchId)),
        };

        // Snapshots are always the ledger's own (base-currency) figures; the
        // currency block says so, same as on the live report.
        $data = $this->currencyConverter->convert($data, $companyId, null, $periodEnd);

        $snapshot = $this->reportSnapshotService->save($type, $periodStart, $periodEnd, $data);

        return $this->success(new ReportSnapshotResource($snapshot), 'Report snapshot saved successfully.', 201);
    }

    public function snapshots(Request $request): JsonResponse
    {
        $snapshots = $this->reportSnapshotService->paginate(
            $request->only('report_type', 'from', 'to'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(ReportSnapshotResource::collection($snapshots));
    }

    protected function snapshotRange(Request $request, \Closure $generate): array
    {
        $validated = Validator::make($request->query(), [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ])->validate();

        return [$validated['from'], $validated['to'], $generate($validated['from'], $validated['to'])];
    }

    protected function snapshotAsOf(Request $request, \Closure $generate): array
    {
        $validated = Validator::make($request->query(), [
            'as_of' => ['required', 'date'],
        ])->validate();

        return [$validated['as_of'], $validated['as_of'], $generate($validated['as_of'])];
    }

    protected function snapshotGeneralLedger(Request $request, int $companyId, ?int $branchId): array
    {
        $validated = Validator::make($request->query(), [
            'account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ])->validate();

        $data = $this->generalLedgerService->generate($companyId, $validated['account_id'], $validated['from'], $validated['to'], $branchId);

        return [$validated['from'], $validated['to'], $data];
    }

    /** The SOF/DEA/project/cost-centre and department filters given on a report request. */
    protected function dimensionFilters(Request $request): array
    {
        return array_filter(DimensionService::of($request->only(DimensionService::KEYS)) + ['department_id' => $request->integer('department_id') ?: null]);
    }
}
