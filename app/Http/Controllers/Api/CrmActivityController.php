<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmActivity\StoreCrmActivityRequest;
use App\Http\Requests\CrmActivity\UpdateCrmActivityRequest;
use App\Http\Resources\CrmActivityResource;
use App\Models\CrmActivity;
use App\Services\Crm\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmActivityController extends Controller
{
    public function __construct(protected ActivityService $activityService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmActivity::class);

        $activities = $this->activityService->paginate(
            $request->only('customer_id', 'lead_id', 'deal_id', 'type', 'status'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmActivityResource::collection($activities));
    }

    public function store(StoreCrmActivityRequest $request): JsonResponse
    {
        $this->authorize('create', CrmActivity::class);

        $activity = $this->activityService->create($request->validated());

        return $this->success(new CrmActivityResource($activity), 'Activity logged successfully.', 201);
    }

    public function show(CrmActivity $crmActivity): JsonResponse
    {
        $this->authorize('view', $crmActivity);

        return $this->success(new CrmActivityResource($crmActivity->load(['customer', 'loggedBy', 'resolvedBy'])));
    }

    public function update(UpdateCrmActivityRequest $request, CrmActivity $crmActivity): JsonResponse
    {
        $this->authorize('update', $crmActivity);

        $activity = $this->activityService->update($crmActivity, $request->validated());

        return $this->success(new CrmActivityResource($activity), 'Activity updated successfully.');
    }

    public function destroy(CrmActivity $crmActivity): JsonResponse
    {
        $this->authorize('delete', $crmActivity);

        $this->activityService->delete($crmActivity);

        return $this->success(null, 'Activity deleted successfully.');
    }

    public function resolve(CrmActivity $crmActivity): JsonResponse
    {
        $this->authorize('resolve', $crmActivity);

        $activity = $this->activityService->resolve($crmActivity);

        return $this->success(new CrmActivityResource($activity), 'Activity resolved successfully.');
    }
}
