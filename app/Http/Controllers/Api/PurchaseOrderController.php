<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $purchaseOrders = $this->purchaseOrderService->paginate(
            $request->only('status', 'supplier_id', 'branch_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(PurchaseOrderResource::collection($purchaseOrders));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $purchaseOrder = $this->purchaseOrderService->create($request->validated());

        return $this->success(new PurchaseOrderResource($purchaseOrder), 'Purchase order created successfully.', 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        return $this->success(new PurchaseOrderResource($purchaseOrder->load('items')));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->update($purchaseOrder, $request->validated());

        return $this->success(new PurchaseOrderResource($purchaseOrder), 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $this->purchaseOrderService->delete($purchaseOrder);

        return $this->success(null, 'Purchase order deleted successfully.');
    }

    public function submit(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('submit', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->submit($purchaseOrder);

        return $this->success(new PurchaseOrderResource($purchaseOrder), 'Purchase order submitted successfully.');
    }

    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->approve($purchaseOrder);

        return $this->success(new PurchaseOrderResource($purchaseOrder), 'Purchase order approved successfully.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->cancel($purchaseOrder);

        return $this->success(new PurchaseOrderResource($purchaseOrder), 'Purchase order cancelled successfully.');
    }

    public function receivingStatus(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        return $this->success($this->purchaseOrderService->receivingStatus($purchaseOrder));
    }
}
