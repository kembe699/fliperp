<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UpdateStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(protected StockAdjustmentService $stockAdjustmentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $adjustments = $this->stockAdjustmentService->paginate(
            $request->only('status', 'warehouse_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(StockAdjustmentResource::collection($adjustments));
    }

    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $this->authorize('create', StockAdjustment::class);

        $adjustment = $this->stockAdjustmentService->create($request->validated());

        return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment created successfully.', 201);
    }

    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('view', $stockAdjustment);

        return $this->success(new StockAdjustmentResource($stockAdjustment->load('items')));
    }

    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('update', $stockAdjustment);

        $stockAdjustment = $this->stockAdjustmentService->update($stockAdjustment, $request->validated());

        return $this->success(new StockAdjustmentResource($stockAdjustment), 'Stock adjustment updated successfully.');
    }

    public function destroy(StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('delete', $stockAdjustment);

        $this->stockAdjustmentService->delete($stockAdjustment);

        return $this->success(null, 'Stock adjustment deleted successfully.');
    }

    public function approve(StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('approve', $stockAdjustment);

        $stockAdjustment = $this->stockAdjustmentService->approve($stockAdjustment);

        return $this->success(new StockAdjustmentResource($stockAdjustment), 'Stock adjustment approved successfully.');
    }
}
