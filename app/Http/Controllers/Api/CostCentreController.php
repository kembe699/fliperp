<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CostCentre\StoreCostCentreRequest;
use App\Http\Requests\CostCentre\UpdateCostCentreRequest;
use App\Http\Resources\CostCentreResource;
use App\Models\CostCentre;
use App\Services\Grants\CostCentreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CostCentreController extends Controller
{
    public function __construct(protected CostCentreService $costCentreService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostCentre::class);

        return $this->paginated(CostCentreResource::collection($this->costCentreService->paginate($request->only('search'), $request->integer('per_page', 15))));
    }

    public function store(StoreCostCentreRequest $request): JsonResponse
    {
        $this->authorize('create', CostCentre::class);

        return $this->success(new CostCentreResource($this->costCentreService->create($request->validated())), 'Saved successfully.', 201);
    }

    public function show(CostCentre $costCentre): JsonResponse
    {
        $this->authorize('view', $costCentre);

        return $this->success(new CostCentreResource($costCentre));
    }

    public function update(UpdateCostCentreRequest $request, CostCentre $costCentre): JsonResponse
    {
        $this->authorize('update', $costCentre);

        return $this->success(new CostCentreResource($this->costCentreService->update($costCentre, $request->validated())), 'Saved successfully.');
    }

    public function destroy(CostCentre $costCentre): JsonResponse
    {
        $this->authorize('delete', $costCentre);

        $this->costCentreService->delete($costCentre);

        return $this->success(null, 'Deleted successfully.');
    }
}
