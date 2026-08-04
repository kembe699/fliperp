<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\Hr\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(protected DepartmentService $departmentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $departments = $this->departmentService->paginate($request->integer('per_page', 15));

        return $this->paginated(DepartmentResource::collection($departments));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $department = $this->departmentService->create($request->validated());

        return $this->success(new DepartmentResource($department), 'Department created successfully.', 201);
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return $this->success(new DepartmentResource($department));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $department = $this->departmentService->update($department, $request->validated());

        return $this->success(new DepartmentResource($department), 'Department updated successfully.');
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        $this->departmentService->delete($department);

        return $this->success(null, 'Department deleted successfully.');
    }
}
