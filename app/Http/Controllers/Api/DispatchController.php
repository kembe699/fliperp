<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\StoreDispatchRequest;
use App\Http\Requests\Dispatch\TrackDispatchRequest;
use App\Http\Requests\Dispatch\UpdateDispatchRequest;
use App\Http\Resources\DeliveryTrackingResource;
use App\Http\Resources\DispatchResource;
use App\Models\Dispatch;
use App\Services\Logistics\DispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function __construct(protected DispatchService $dispatchService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Dispatch::class);

        $dispatches = $this->dispatchService->paginate($request->integer('per_page', 15));

        return $this->paginated(DispatchResource::collection($dispatches));
    }

    public function store(StoreDispatchRequest $request): JsonResponse
    {
        $this->authorize('create', Dispatch::class);

        $dispatch = $this->dispatchService->create($request->validated());

        return $this->success(new DispatchResource($dispatch), 'Dispatch created successfully.', 201);
    }

    public function show(Dispatch $dispatch): JsonResponse
    {
        $this->authorize('view', $dispatch);

        return $this->success(new DispatchResource($dispatch->load(['items', 'tracking'])));
    }

    public function update(UpdateDispatchRequest $request, Dispatch $dispatch): JsonResponse
    {
        $this->authorize('update', $dispatch);

        $dispatch = $this->dispatchService->update($dispatch, $request->validated());

        return $this->success(new DispatchResource($dispatch), 'Dispatch updated successfully.');
    }

    public function destroy(Dispatch $dispatch): JsonResponse
    {
        $this->authorize('delete', $dispatch);

        $this->dispatchService->delete($dispatch);

        return $this->success(null, 'Dispatch deleted successfully.');
    }

    public function track(TrackDispatchRequest $request, Dispatch $dispatch): JsonResponse
    {
        $this->authorize('track', $dispatch);

        $tracking = $this->dispatchService->track($dispatch, $request->validated());

        return $this->success(new DeliveryTrackingResource($tracking), 'Delivery tracking entry recorded successfully.', 201);
    }
}
