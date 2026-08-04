<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeProject\StoreMeProjectRequest;
use App\Http\Requests\MeProject\UpdateMeProjectRequest;
use App\Http\Resources\MeProjectResource;
use App\Models\MeProject;
use App\Services\Me\MeDashboardService;
use App\Services\Me\MeProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeProjectController extends Controller
{
    public function __construct(
        protected MeProjectService $meProjectService,
        protected MeDashboardService $meDashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MeProject::class);

        $projects = $this->meProjectService->paginate($request->integer('per_page', 15));

        return $this->paginated(MeProjectResource::collection($projects));
    }

    public function store(StoreMeProjectRequest $request): JsonResponse
    {
        $this->authorize('create', MeProject::class);

        $project = $this->meProjectService->create($request->validated());

        return $this->success(new MeProjectResource($project), 'M&E project created successfully.', 201);
    }

    public function show(MeProject $meProject): JsonResponse
    {
        $this->authorize('view', $meProject);

        return $this->success(new MeProjectResource($meProject));
    }

    public function update(UpdateMeProjectRequest $request, MeProject $meProject): JsonResponse
    {
        $this->authorize('update', $meProject);

        $meProject = $this->meProjectService->update($meProject, $request->validated());

        return $this->success(new MeProjectResource($meProject), 'M&E project updated successfully.');
    }

    public function destroy(MeProject $meProject): JsonResponse
    {
        $this->authorize('delete', $meProject);

        $this->meProjectService->delete($meProject);

        return $this->success(null, 'M&E project deleted successfully.');
    }

    public function dashboard(MeProject $meProject): JsonResponse
    {
        $this->authorize('view', $meProject);

        return $this->success($this->meDashboardService->dashboard($meProject));
    }
}
