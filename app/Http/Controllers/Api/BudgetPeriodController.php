<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetPeriod\StoreBudgetPeriodRequest;
use App\Http\Requests\BudgetPeriod\UpdateBudgetPeriodRequest;
use App\Http\Resources\BudgetPeriodResource;
use App\Models\BudgetPeriod;
use App\Services\Budgeting\BudgetPeriodService;
use App\Services\Budgeting\BudgetVsActualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetPeriodController extends Controller
{
    public function __construct(
        protected BudgetPeriodService $budgetPeriodService,
        protected BudgetVsActualService $budgetVsActualService,
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

    public function vsActual(BudgetPeriod $budgetPeriod): JsonResponse
    {
        $this->authorize('view', $budgetPeriod);

        return $this->success($this->budgetVsActualService->compare($budgetPeriod));
    }
}
