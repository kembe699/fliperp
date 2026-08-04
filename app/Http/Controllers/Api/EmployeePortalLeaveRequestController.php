<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeePortal\StoreEmployeePortalLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Services\Hr\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeePortalLeaveRequestController extends Controller
{
    public function __construct(protected LeaveRequestService $leaveRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $leaveRequests = $this->leaveRequestService->paginate(
            ['employee_id' => $employee->id],
            $request->integer('per_page', 15),
        );

        return $this->paginated(LeaveRequestResource::collection($leaveRequests));
    }

    public function store(StoreEmployeePortalLeaveRequestRequest $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $leaveRequest = $this->leaveRequestService->create([
            ...$request->validated(),
            'employee_id' => $employee->id,
        ]);

        return $this->success(new LeaveRequestResource($leaveRequest), 'Leave request submitted successfully.', 201);
    }

    protected function currentEmployee(Request $request)
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => ['No employee record is linked to this account.'],
            ]);
        }

        return $employee;
    }
}
