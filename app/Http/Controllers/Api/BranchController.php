<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\Branch\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(protected BranchService $branchService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $branches = $this->branchService->paginate($request->integer('per_page', 15));

        return $this->paginated(BranchResource::collection($branches));
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $branch = $this->branchService->create($request->validated());

        return $this->success(new BranchResource($branch), 'Branch created successfully.', 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return $this->success(new BranchResource($branch));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $branch = $this->branchService->update($branch, $request->validated());

        return $this->success(new BranchResource($branch), 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $this->branchService->delete($branch);

        return $this->success(null, 'Branch deleted successfully.');
    }
}
