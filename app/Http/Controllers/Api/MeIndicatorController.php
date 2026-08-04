<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeIndicator\StoreMeIndicatorRequest;
use App\Http\Requests\MeIndicator\UpdateMeIndicatorRequest;
use App\Http\Resources\MeIndicatorResource;
use App\Models\MeIndicator;
use App\Services\Me\MeIndicatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeIndicatorController extends Controller
{
    public function __construct(protected MeIndicatorService $meIndicatorService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MeIndicator::class);

        $indicators = $this->meIndicatorService->paginate($request->only('me_project_id'), $request->integer('per_page', 15));

        return $this->paginated(MeIndicatorResource::collection($indicators));
    }

    public function store(StoreMeIndicatorRequest $request): JsonResponse
    {
        $this->authorize('create', MeIndicator::class);

        $indicator = $this->meIndicatorService->create($request->validated());

        return $this->success(new MeIndicatorResource($indicator), 'M&E indicator created successfully.', 201);
    }

    public function show(MeIndicator $meIndicator): JsonResponse
    {
        $this->authorize('view', $meIndicator);

        return $this->success(new MeIndicatorResource($meIndicator));
    }

    public function update(UpdateMeIndicatorRequest $request, MeIndicator $meIndicator): JsonResponse
    {
        $this->authorize('update', $meIndicator);

        $meIndicator = $this->meIndicatorService->update($meIndicator, $request->validated());

        return $this->success(new MeIndicatorResource($meIndicator), 'M&E indicator updated successfully.');
    }

    public function destroy(MeIndicator $meIndicator): JsonResponse
    {
        $this->authorize('delete', $meIndicator);

        $this->meIndicatorService->delete($meIndicator);

        return $this->success(null, 'M&E indicator deleted successfully.');
    }
}
