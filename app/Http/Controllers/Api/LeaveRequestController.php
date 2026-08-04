<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequest\StoreLeaveRequestRequest;
use App\Http\Requests\LeaveRequest\UpdateLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\Hr\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(protected LeaveRequestService $leaveRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $leaveRequests = $this->leaveRequestService->paginate($request->only('employee_id', 'status'), $request->integer('per_page', 15));

        return $this->paginated(LeaveRequestResource::collection($leaveRequests));
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $leaveRequest = $this->leaveRequestService->create($request->validated());

        return $this->success(new LeaveRequestResource($leaveRequest), 'Leave request submitted successfully.', 201);
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('view', $leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest));
    }

    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('update', $leaveRequest);

        $leaveRequest = $this->leaveRequestService->update($leaveRequest, $request->validated());

        return $this->success(new LeaveRequestResource($leaveRequest), 'Leave request updated successfully.');
    }

    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $this->leaveRequestService->delete($leaveRequest);

        return $this->success(null, 'Leave request deleted successfully.');
    }

    public function approve(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        $leaveRequest = $this->leaveRequestService->approve($leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest), 'Leave request approved successfully.');
    }

    public function reject(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('reject', $leaveRequest);

        $leaveRequest = $this->leaveRequestService->reject($leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest), 'Leave request rejected successfully.');
    }
}
