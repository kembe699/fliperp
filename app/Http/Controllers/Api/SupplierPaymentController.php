<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierPayment\StoreSupplierPaymentRequest;
use App\Http\Requests\SupplierPayment\UpdateSupplierPaymentRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\SupplierPayment;
use App\Services\Procurement\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function __construct(protected SupplierPaymentService $supplierPaymentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $payments = $this->supplierPaymentService->paginate(
            $request->only('supplier_bill_id', 'supplier_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(SupplierPaymentResource::collection($payments));
    }

    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $this->authorize('create', SupplierPayment::class);

        $payment = $this->supplierPaymentService->create($request->validated());

        return $this->success(new SupplierPaymentResource($payment), 'Supplier payment recorded successfully.', 201);
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('view', $supplierPayment);

        return $this->success(new SupplierPaymentResource($supplierPayment));
    }

    public function update(UpdateSupplierPaymentRequest $request, SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('update', $supplierPayment);

        $supplierPayment = $this->supplierPaymentService->update($supplierPayment, $request->validated());

        return $this->success(new SupplierPaymentResource($supplierPayment), 'Supplier payment updated successfully.');
    }

    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        $this->authorize('delete', $supplierPayment);

        $this->supplierPaymentService->delete($supplierPayment);

        return $this->success(null, 'Supplier payment deleted successfully.');
    }
}
