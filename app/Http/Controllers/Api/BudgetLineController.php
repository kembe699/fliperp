<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetLine\StoreBudgetLineRequest;
use App\Http\Requests\BudgetLine\UpdateBudgetLineRequest;
use App\Http\Resources\BudgetLineResource;
use App\Models\BudgetLine;
use App\Services\Budgeting\BudgetLineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetLineController extends Controller
{
    public function __construct(protected BudgetLineService $budgetLineService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BudgetLine::class);

        $budgetLines = $this->budgetLineService->paginate($request->only('budget_period_id'), $request->integer('per_page', 15));

        return $this->paginated(BudgetLineResource::collection($budgetLines));
    }

    public function store(StoreBudgetLineRequest $request): JsonResponse
    {
        $this->authorize('create', BudgetLine::class);

        $budgetLine = $this->budgetLineService->create($request->validated());

        return $this->success(new BudgetLineResource($budgetLine), 'Budget line created successfully.', 201);
    }

    public function show(BudgetLine $budgetLine): JsonResponse
    {
        $this->authorize('view', $budgetLine);

        return $this->success(new BudgetLineResource($budgetLine));
    }

    public function update(UpdateBudgetLineRequest $request, BudgetLine $budgetLine): JsonResponse
    {
        $this->authorize('update', $budgetLine);

        $budgetLine = $this->budgetLineService->update($budgetLine, $request->validated());

        return $this->success(new BudgetLineResource($budgetLine), 'Budget line updated successfully.');
    }

    public function destroy(BudgetLine $budgetLine): JsonResponse
    {
        $this->authorize('delete', $budgetLine);

        $this->budgetLineService->delete($budgetLine);

        return $this->success(null, 'Budget line deleted successfully.');
    }
}
