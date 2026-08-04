<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeResult\StoreMeResultRequest;
use App\Http\Requests\MeResult\UpdateMeResultRequest;
use App\Http\Resources\MeResultResource;
use App\Models\MeResult;
use App\Services\Me\MeResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeResultController extends Controller
{
    public function __construct(protected MeResultService $meResultService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MeResult::class);

        $results = $this->meResultService->paginate($request->only('me_indicator_id'), $request->integer('per_page', 15));

        return $this->paginated(MeResultResource::collection($results));
    }

    public function store(StoreMeResultRequest $request): JsonResponse
    {
        $this->authorize('create', MeResult::class);

        $result = $this->meResultService->create($request->validated());

        return $this->success(new MeResultResource($result), 'M&E result recorded successfully.', 201);
    }

    public function show(MeResult $meResult): JsonResponse
    {
        $this->authorize('view', $meResult);

        return $this->success(new MeResultResource($meResult));
    }

    public function update(UpdateMeResultRequest $request, MeResult $meResult): JsonResponse
    {
        $this->authorize('update', $meResult);

        $meResult = $this->meResultService->update($meResult, $request->validated());

        return $this->success(new MeResultResource($meResult), 'M&E result updated successfully.');
    }

    public function destroy(MeResult $meResult): JsonResponse
    {
        $this->authorize('delete', $meResult);

        $this->meResultService->delete($meResult);

        return $this->success(null, 'M&E result deleted successfully.');
    }
}
