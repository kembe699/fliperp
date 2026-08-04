<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use App\Services\Hr\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct(protected PositionService $positionService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Position::class);

        $positions = $this->positionService->paginate($request->integer('per_page', 15));

        return $this->paginated(PositionResource::collection($positions));
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $this->authorize('create', Position::class);

        $position = $this->positionService->create($request->validated());

        return $this->success(new PositionResource($position), 'Position created successfully.', 201);
    }

    public function show(Position $position): JsonResponse
    {
        $this->authorize('view', $position);

        return $this->success(new PositionResource($position));
    }

    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $this->authorize('update', $position);

        $position = $this->positionService->update($position, $request->validated());

        return $this->success(new PositionResource($position), 'Position updated successfully.');
    }

    public function destroy(Position $position): JsonResponse
    {
        $this->authorize('delete', $position);

        $this->positionService->delete($position);

        return $this->success(null, 'Position deleted successfully.');
    }
}
