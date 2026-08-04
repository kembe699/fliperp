<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeActivity\StoreMeActivityRequest;
use App\Http\Requests\MeActivity\UpdateMeActivityRequest;
use App\Http\Resources\MeActivityResource;
use App\Models\MeActivity;
use App\Services\Me\MeActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeActivityController extends Controller
{
    public function __construct(protected MeActivityService $meActivityService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MeActivity::class);

        $activities = $this->meActivityService->paginate($request->only('me_project_id'), $request->integer('per_page', 15));

        return $this->paginated(MeActivityResource::collection($activities));
    }

    public function store(StoreMeActivityRequest $request): JsonResponse
    {
        $this->authorize('create', MeActivity::class);

        $activity = $this->meActivityService->create($request->validated());

        return $this->success(new MeActivityResource($activity), 'M&E activity created successfully.', 201);
    }

    public function show(MeActivity $meActivity): JsonResponse
    {
        $this->authorize('view', $meActivity);

        return $this->success(new MeActivityResource($meActivity));
    }

    public function update(UpdateMeActivityRequest $request, MeActivity $meActivity): JsonResponse
    {
        $this->authorize('update', $meActivity);

        $meActivity = $this->meActivityService->update($meActivity, $request->validated());

        return $this->success(new MeActivityResource($meActivity), 'M&E activity updated successfully.');
    }

    public function destroy(MeActivity $meActivity): JsonResponse
    {
        $this->authorize('delete', $meActivity);

        $this->meActivityService->delete($meActivity);

        return $this->success(null, 'M&E activity deleted successfully.');
    }
}
