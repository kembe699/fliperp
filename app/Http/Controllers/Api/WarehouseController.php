<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\Inventory\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(protected WarehouseService $warehouseService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = $this->warehouseService->paginate($request->integer('per_page', 15));

        return $this->paginated(WarehouseResource::collection($warehouses));
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $this->warehouseService->create($request->validated());

        return $this->success(new WarehouseResource($warehouse), 'Warehouse created successfully.', 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        return $this->success(new WarehouseResource($warehouse));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse = $this->warehouseService->update($warehouse, $request->validated());

        return $this->success(new WarehouseResource($warehouse), 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $this->warehouseService->delete($warehouse);

        return $this->success(null, 'Warehouse deleted successfully.');
    }
}
