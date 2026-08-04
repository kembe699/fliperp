<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierBill\StoreSupplierBillRequest;
use App\Http\Requests\SupplierBill\UpdateSupplierBillRequest;
use App\Http\Resources\SupplierBillResource;
use App\Models\SupplierBill;
use App\Services\Procurement\SupplierBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierBillController extends Controller
{
    public function __construct(protected SupplierBillService $supplierBillService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierBill::class);

        $bills = $this->supplierBillService->paginate(
            $request->only('status', 'supplier_id', 'from', 'to', 'search'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(SupplierBillResource::collection($bills));
    }

    public function store(StoreSupplierBillRequest $request): JsonResponse
    {
        $this->authorize('create', SupplierBill::class);

        $bill = $this->supplierBillService->create($request->validated());

        return $this->success(new SupplierBillResource($bill), 'Supplier bill created successfully.', 201);
    }

    public function show(SupplierBill $supplierBill): JsonResponse
    {
        $this->authorize('view', $supplierBill);

        $supplierBill = $this->supplierBillService->refreshStatus($supplierBill);

        return $this->success(new SupplierBillResource($supplierBill));
    }

    public function update(UpdateSupplierBillRequest $request, SupplierBill $supplierBill): JsonResponse
    {
        $this->authorize('update', $supplierBill);

        $supplierBill = $this->supplierBillService->update($supplierBill, $request->validated());

        return $this->success(new SupplierBillResource($supplierBill), 'Supplier bill updated successfully.');
    }

    public function destroy(SupplierBill $supplierBill): JsonResponse
    {
        $this->authorize('delete', $supplierBill);

        $this->supplierBillService->delete($supplierBill);

        return $this->success(null, 'Supplier bill deleted successfully.');
    }
}
