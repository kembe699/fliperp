<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveType\StoreLeaveTypeRequest;
use App\Http\Requests\LeaveType\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Services\Hr\LeaveTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function __construct(protected LeaveTypeService $leaveTypeService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        $leaveTypes = $this->leaveTypeService->paginate($request->integer('per_page', 15));

        return $this->paginated(LeaveTypeResource::collection($leaveTypes));
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveType::class);

        $leaveType = $this->leaveTypeService->create($request->validated());

        return $this->success(new LeaveTypeResource($leaveType), 'Leave type created successfully.', 201);
    }

    public function show(LeaveType $leaveType): JsonResponse
    {
        $this->authorize('view', $leaveType);

        return $this->success(new LeaveTypeResource($leaveType));
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $this->authorize('update', $leaveType);

        $leaveType = $this->leaveTypeService->update($leaveType, $request->validated());

        return $this->success(new LeaveTypeResource($leaveType), 'Leave type updated successfully.');
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $this->authorize('delete', $leaveType);

        $this->leaveTypeService->delete($leaveType);

        return $this->success(null, 'Leave type deleted successfully.');
    }
}
