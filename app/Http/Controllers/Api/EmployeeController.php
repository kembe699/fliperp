<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeePhotoRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $employeeService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->employeeService->paginate($request->integer('per_page', 15));

        return $this->paginated(EmployeeResource::collection($employees));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->employeeService->create($request->validated());

        return $this->success(new EmployeeResource($employee), 'Employee created successfully.', 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success(new EmployeeResource($employee));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($employee, $request->validated());

        return $this->success(new EmployeeResource($employee), 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);

        $this->employeeService->delete($employee);

        return $this->success(null, 'Employee deleted successfully.');
    }

    public function uploadPhoto(StoreEmployeePhotoRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $this->employeeService->uploadPhoto($employee, $request->file('image'));

        return $this->success(new EmployeeResource($employee), 'Employee photo uploaded successfully.');
    }

    public function deletePhoto(Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $this->employeeService->deletePhoto($employee);

        return $this->success(new EmployeeResource($employee), 'Employee photo removed successfully.');
    }

    public function createPortalAccount(Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $result = $this->employeeService->createPortalAccount($employee);

        return $this->success([
            'employee' => new EmployeeResource($result['employee']),
            'temporary_password' => $result['temporary_password'],
        ], 'Portal login created successfully. Share the temporary password with the employee securely — it will not be shown again.', 201);
    }
}
