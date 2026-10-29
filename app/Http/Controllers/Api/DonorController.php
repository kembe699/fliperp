<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\StoreDonorRequest;
use App\Http\Requests\Donor\UpdateDonorRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donor;
use App\Services\Grants\DonorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    public function __construct(protected DonorService $donorService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Donor::class);

        return $this->paginated(DonorResource::collection($this->donorService->paginate($request->only('search'), $request->integer('per_page', 15))));
    }

    public function store(StoreDonorRequest $request): JsonResponse
    {
        $this->authorize('create', Donor::class);

        return $this->success(new DonorResource($this->donorService->create($request->validated())), 'Saved successfully.', 201);
    }

    public function show(Donor $donor): JsonResponse
    {
        $this->authorize('view', $donor);

        return $this->success(new DonorResource($donor));
    }

    public function update(UpdateDonorRequest $request, Donor $donor): JsonResponse
    {
        $this->authorize('update', $donor);

        return $this->success(new DonorResource($this->donorService->update($donor, $request->validated())), 'Saved successfully.');
    }

    public function destroy(Donor $donor): JsonResponse
    {
        $this->authorize('delete', $donor);

        $this->donorService->delete($donor);

        return $this->success(null, 'Deleted successfully.');
    }
}
