<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\Logistics\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function __construct(protected VehicleService $vehicleService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = $this->vehicleService->paginate($request->integer('per_page', 15));

        return $this->paginated(VehicleResource::collection($vehicles));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $vehicle = $this->vehicleService->create($request->validated());

        return $this->success(new VehicleResource($vehicle), 'Vehicle created successfully.', 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return $this->success(new VehicleResource($vehicle));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle = $this->vehicleService->update($vehicle, $request->validated());

        return $this->success(new VehicleResource($vehicle), 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $this->vehicleService->delete($vehicle);

        return $this->success(null, 'Vehicle deleted successfully.');
    }
}
