<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestaurantTable\StoreRestaurantTableRequest;
use App\Http\Requests\RestaurantTable\UpdateRestaurantTableRequest;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use App\Services\Pos\RestaurantTableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantTableController extends Controller
{
    public function __construct(protected RestaurantTableService $restaurantTableService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RestaurantTable::class);

        $tables = $this->restaurantTableService->paginate($request->integer('per_page', 15));

        return $this->paginated(RestaurantTableResource::collection($tables));
    }

    public function store(StoreRestaurantTableRequest $request): JsonResponse
    {
        $this->authorize('create', RestaurantTable::class);

        $table = $this->restaurantTableService->create($request->validated());

        return $this->success(new RestaurantTableResource($table), 'Restaurant table created successfully.', 201);
    }

    public function show(RestaurantTable $restaurantTable): JsonResponse
    {
        $this->authorize('view', $restaurantTable);

        return $this->success(new RestaurantTableResource($restaurantTable));
    }

    public function update(UpdateRestaurantTableRequest $request, RestaurantTable $restaurantTable): JsonResponse
    {
        $this->authorize('update', $restaurantTable);

        $restaurantTable = $this->restaurantTableService->update($restaurantTable, $request->validated());

        return $this->success(new RestaurantTableResource($restaurantTable), 'Restaurant table updated successfully.');
    }

    public function destroy(RestaurantTable $restaurantTable): JsonResponse
    {
        $this->authorize('delete', $restaurantTable);

        $this->restaurantTableService->delete($restaurantTable);

        return $this->success(null, 'Restaurant table deleted successfully.');
    }
}
