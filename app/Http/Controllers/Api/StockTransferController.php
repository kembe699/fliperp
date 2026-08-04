<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransfer\CompleteStockTransferRequest;
use App\Http\Requests\StockTransfer\StoreStockTransferRequest;
use App\Http\Requests\StockTransfer\UpdateStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(protected StockTransferService $stockTransferService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $transfers = $this->stockTransferService->paginate(
            $request->only('status', 'from_warehouse_id', 'to_warehouse_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(StockTransferResource::collection($transfers));
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);

        $transfer = $this->stockTransferService->create($request->validated());

        return $this->success(new StockTransferResource($transfer), 'Stock transfer created successfully.', 201);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('view', $stockTransfer);

        return $this->success(new StockTransferResource($stockTransfer->load('items')));
    }

    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('update', $stockTransfer);

        $stockTransfer = $this->stockTransferService->update($stockTransfer, $request->validated());

        return $this->success(new StockTransferResource($stockTransfer), 'Stock transfer updated successfully.');
    }

    public function destroy(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('delete', $stockTransfer);

        $this->stockTransferService->delete($stockTransfer);

        return $this->success(null, 'Stock transfer deleted successfully.');
    }

    public function markInTransit(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('update', $stockTransfer);

        $stockTransfer = $this->stockTransferService->markInTransit($stockTransfer);

        return $this->success(new StockTransferResource($stockTransfer), 'Stock transfer marked in transit.');
    }

    public function complete(CompleteStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('complete', $stockTransfer);

        $stockTransfer = $this->stockTransferService->complete($stockTransfer, (bool) $request->boolean('allow_negative_stock'));

        return $this->success(new StockTransferResource($stockTransfer), 'Stock transfer completed successfully.');
    }

    public function cancel(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('update', $stockTransfer);

        $stockTransfer = $this->stockTransferService->cancel($stockTransfer);

        return $this->success(new StockTransferResource($stockTransfer), 'Stock transfer cancelled successfully.');
    }
}
