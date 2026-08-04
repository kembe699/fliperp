<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\Permission\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(protected PermissionService $permissionService) {}

    public function index(Request $request): JsonResponse
    {
        $permissions = $this->permissionService->paginate($request->integer('per_page', 15));

        return $this->paginated(PermissionResource::collection($permissions));
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->create($request->validated());

        return $this->success(new PermissionResource($permission), 'Permission created successfully.', 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return $this->success(new PermissionResource($permission));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->update($permission, $request->validated());

        return $this->success(new PermissionResource($permission), 'Permission updated successfully.');
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $this->permissionService->delete($permission);

        return $this->success(null, 'Permission deleted successfully.');
    }
}
