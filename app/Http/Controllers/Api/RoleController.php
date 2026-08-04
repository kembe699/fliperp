<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\Role\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->roleService->paginate($request->integer('per_page', 15));

        return $this->paginated(RoleResource::collection($roles));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return $this->success(new RoleResource($role), 'Role created successfully.', 201);
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success(new RoleResource($role->load('permissions')));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return $this->success(new RoleResource($role), 'Role updated successfully.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->delete($role);

        return $this->success(null, 'Role deleted successfully.');
    }
}
