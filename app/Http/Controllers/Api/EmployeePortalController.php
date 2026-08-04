<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeePortal\ClockRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\Attendance;
use App\Models\LeaveType;
use App\Services\EmployeePortal\ClockService;
use App\Services\EmployeePortal\LeaveSummaryService;
use App\Services\Hr\AttendanceGeofenceService;
use App\Services\Hr\AttendanceQrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeePortalController extends Controller
{
    public function __construct(
        protected ClockService $clockService,
        protected LeaveSummaryService $leaveSummaryService,
        protected AttendanceQrTokenService $qrTokenService,
        protected AttendanceGeofenceService $geofenceService,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request)->load(['branch', 'department', 'position']);

        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', now()->toDateString())
            ->first();

        $recentAttendance = Attendance::where('employee_id', $employee->id)
            ->latest('date')
            ->limit(10)
            ->get();

        $leaveSummary = $this->leaveSummaryService->summarize($employee);

        return $this->success([
            'employee' => new EmployeeResource($employee),
            'today_attendance' => $todayAttendance ? new AttendanceResource($todayAttendance) : null,
            'recent_attendance' => AttendanceResource::collection($recentAttendance),
            'leave_balance' => [
                'total_entitled_days' => $leaveSummary['total_entitled_days'],
                'total_taken_days' => $leaveSummary['total_taken_days'],
                'total_remaining_days' => $leaveSummary['total_remaining_days'],
            ],
        ]);
    }

    public function leaveSummary(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        return $this->success($this->leaveSummaryService->summarize($employee));
    }

    /**
     * The "employee" role is granted zero Spatie permissions on purpose, so
     * the portal can't reuse the main /leave-types endpoint (gated by
     * leave-types.view) — this gives just enough read-only data to populate
     * the "Request Leave" form's leave type picker.
     */
    public function leaveTypes(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $leaveTypes = LeaveType::where('company_id', $employee->company_id)->orderBy('name')->get();

        return $this->success(LeaveTypeResource::collection($leaveTypes));
    }

    /**
     * A read-only dry run of the same token + geofence checks clock-in/out
     * perform, so the clock page can show "you're at the office" or "too
     * far" *before* the employee taps the button — without creating (or
     * risking accidentally creating) an attendance record.
     */
    public function checkLocation(ClockRequest $request): JsonResponse
    {
        $data = $request->validated();

        $validToken = $this->qrTokenService->isValidToken((int) $data['branch_id'], $data['token']);

        if (! $validToken) {
            return $this->success(['within_range' => false, 'reason' => 'Invalid QR code.']);
        }

        $withinRange = $this->geofenceService->isWithinBranchGeofence(
            (int) $data['branch_id'],
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        return $this->success([
            'within_range' => $withinRange,
            'reason' => $withinRange ? null : 'You must be within the office location to clock in/out.',
        ]);
    }

    public function clockIn(ClockRequest $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $data = $request->validated();

        $attendance = $this->clockService->clockIn(
            $employee,
            (int) $data['branch_id'],
            $data['token'],
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        return $this->success(new AttendanceResource($attendance), 'Clocked in successfully.');
    }

    public function clockOut(ClockRequest $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $data = $request->validated();

        $attendance = $this->clockService->clockOut(
            $employee,
            (int) $data['branch_id'],
            $data['token'],
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        return $this->success(new AttendanceResource($attendance), 'Clocked out successfully.');
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
