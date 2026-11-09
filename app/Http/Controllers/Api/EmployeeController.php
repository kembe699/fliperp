<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeePhotoRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Position;
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

        $employee = $this->employeeService->create($this->withPosition($request->validated()));

        return $this->success(new EmployeeResource($employee), 'Employee created successfully.', 201);
    }

    /**
     * A typed position title becomes position_id: the department's position with
     * that title (any letter case), or a new one in that department.
     */
    protected function withPosition(array $data, ?Employee $employee = null): array
    {
        $title = trim((string) ($data['position_title'] ?? ''));
        unset($data['position_title']);
        if ($title === '') {
            return $data;
        }

        $companyId = request()->user()->company_id;
        $departmentId = $data['department_id'] ?? $employee?->department_id;
        $position = Position::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->first()
            ?? Position::create(['company_id' => $companyId, 'department_id' => $departmentId, 'title' => $title]);

        $data['position_id'] = $position->id;

        return $data;
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success(new EmployeeResource($employee));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($employee, $this->withPosition($request->validated(), $employee));

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
