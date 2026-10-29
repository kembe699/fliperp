<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetPeriod\StoreBudgetPeriodRequest;
use App\Http\Requests\BudgetPeriod\UpdateBudgetPeriodRequest;
use App\Http\Resources\BudgetPeriodResource;
use App\Models\BudgetPeriod;
use App\Services\Budgeting\BudgetPeriodService;
use App\Services\Budgeting\BudgetVsActualService;
use App\Services\Grants\DimensionService;
use App\Services\Reporting\ReportCurrencyConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetPeriodController extends Controller
{
    public function __construct(
        protected BudgetPeriodService $budgetPeriodService,
        protected BudgetVsActualService $budgetVsActualService,
        protected ReportCurrencyConverter $currencyConverter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BudgetPeriod::class);

        $budgetPeriods = $this->budgetPeriodService->paginate($request->integer('per_page', 15));

        return $this->paginated(BudgetPeriodResource::collection($budgetPeriods));
    }

    public function store(StoreBudgetPeriodRequest $request): JsonResponse
    {
        $this->authorize('create', BudgetPeriod::class);

        $budgetPeriod = $this->budgetPeriodService->create($request->validated());

        return $this->success(new BudgetPeriodResource($budgetPeriod), 'Budget period created successfully.', 201);
    }

    public function show(BudgetPeriod $budgetPeriod): JsonResponse
    {
        $this->authorize('view', $budgetPeriod);

        return $this->success(new BudgetPeriodResource($budgetPeriod));
    }

    public function update(UpdateBudgetPeriodRequest $request, BudgetPeriod $budgetPeriod): JsonResponse
    {
        $this->authorize('update', $budgetPeriod);

        $budgetPeriod = $this->budgetPeriodService->update($budgetPeriod, $request->validated());

        return $this->success(new BudgetPeriodResource($budgetPeriod), 'Budget period updated successfully.');
    }

    public function destroy(BudgetPeriod $budgetPeriod): JsonResponse
    {
        $this->authorize('delete', $budgetPeriod);

        $this->budgetPeriodService->delete($budgetPeriod);

        return $this->success(null, 'Budget period deleted successfully.');
    }

    /**
     * Budget vs actual for the period, optionally only the lines coded to a
     * SOF/project/cost centre, and optionally shown in another currency (at
     * the rate on the period's end date) like the other reports.
     */
    public function vsActual(Request $request, BudgetPeriod $budgetPeriod): JsonResponse
    {
        $this->authorize('view', $budgetPeriod);

        $request->validate(DimensionService::filterRules() + ['currency' => ['nullable', 'string', 'size:3']]);

        $lines = $this->budgetVsActualService->compare($budgetPeriod, DimensionService::of($request->only(DimensionService::KEYS)));
        $budgeted = round(array_sum(array_column($lines, 'budgeted_amount')), 2);
        $actual = round(array_sum(array_column($lines, 'actual_amount')), 2);

        $data = $this->currencyConverter->convert([
            'lines' => $lines,
            'totals' => ['budgeted_amount' => $budgeted, 'actual_amount' => $actual, 'variance' => round($budgeted - $actual, 2)],
        ], $budgetPeriod->company_id, $request->input('currency'), $budgetPeriod->end_date->toDateString());

        return $this->success($data);
    }
}
