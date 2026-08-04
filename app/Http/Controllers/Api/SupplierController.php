<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Procurement\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(protected SupplierService $supplierService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->supplierService->paginate($request->integer('per_page', 15));

        return $this->paginated(SupplierResource::collection($suppliers));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->supplierService->create($request->validated());

        return $this->success(new SupplierResource($supplier), 'Supplier created successfully.', 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return $this->success(new SupplierResource($supplier));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $supplier = $this->supplierService->update($supplier, $request->validated());

        return $this->success(new SupplierResource($supplier), 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $this->supplierService->delete($supplier);

        return $this->success(null, 'Supplier deleted successfully.');
    }

    public function statement(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return $this->success($this->supplierService->statement($supplier));
    }
}
