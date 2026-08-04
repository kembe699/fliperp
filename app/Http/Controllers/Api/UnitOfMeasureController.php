<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitOfMeasure\StoreUnitOfMeasureRequest;
use App\Http\Requests\UnitOfMeasure\UpdateUnitOfMeasureRequest;
use App\Http\Resources\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\UnitOfMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    public function __construct(protected UnitOfMeasureService $unitOfMeasureService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UnitOfMeasure::class);

        $units = $this->unitOfMeasureService->paginate($request->integer('per_page', 15));

        return $this->paginated(UnitOfMeasureResource::collection($units));
    }

    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        $this->authorize('create', UnitOfMeasure::class);

        $unit = $this->unitOfMeasureService->create($request->validated());

        return $this->success(new UnitOfMeasureResource($unit), 'Unit of measure created successfully.', 201);
    }

    public function show(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('view', $unitOfMeasure);

        return $this->success(new UnitOfMeasureResource($unitOfMeasure));
    }

    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('update', $unitOfMeasure);

        $unitOfMeasure = $this->unitOfMeasureService->update($unitOfMeasure, $request->validated());

        return $this->success(new UnitOfMeasureResource($unitOfMeasure), 'Unit of measure updated successfully.');
    }

    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->authorize('delete', $unitOfMeasure);

        $this->unitOfMeasureService->delete($unitOfMeasure);

        return $this->success(null, 'Unit of measure deleted successfully.');
    }
}
