<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\Hr\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $records = $this->attendanceService->paginate(
            $request->only('employee_id', 'date_from', 'date_to', 'status', 'department_id', 'search'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(AttendanceResource::collection($records));
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        $record = $this->attendanceService->create($request->validated());

        return $this->success(new AttendanceResource($record), 'Attendance recorded successfully.', 201);
    }

    public function show(Attendance $attendance): JsonResponse
    {
        $this->authorize('view', $attendance);

        return $this->success(new AttendanceResource($attendance));
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('update', $attendance);

        $attendance = $this->attendanceService->update($attendance, $request->validated());

        return $this->success(new AttendanceResource($attendance), 'Attendance updated successfully.');
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->authorize('delete', $attendance);

        $this->attendanceService->delete($attendance);

        return $this->success(null, 'Attendance deleted successfully.');
    }
}
