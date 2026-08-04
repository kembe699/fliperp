<?php

namespace App\Services\EmployeePortal;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\Hr\AttendanceGeofenceService;
use App\Services\Hr\AttendanceQrTokenService;
use Illuminate\Validation\ValidationException;

class ClockService
{
    public function __construct(
        protected AttendanceQrTokenService $qrTokenService,
        protected AttendanceGeofenceService $geofenceService,
    ) {}

    public function clockIn(Employee $employee, int $branchId, string $token, float $latitude, float $longitude): Attendance
    {
        $this->validateTokenAndLocation($branchId, $token, $latitude, $longitude);

        $attendance = Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
        ]);

        if ($attendance->exists && $attendance->clock_in) {
            throw ValidationException::withMessages([
                'clock_in' => ['You have already clocked in today.'],
            ]);
        }

        $attendance->clock_in = now()->format('H:i:s');
        $attendance->status = 'present';
        $attendance->save();

        return $attendance;
    }

    public function clockOut(Employee $employee, int $branchId, string $token, float $latitude, float $longitude): Attendance
    {
        $this->validateTokenAndLocation($branchId, $token, $latitude, $longitude);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', now()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->clock_in) {
            throw ValidationException::withMessages([
                'clock_in' => ['You must clock in before you can clock out.'],
            ]);
        }

        if ($attendance->clock_out) {
            throw ValidationException::withMessages([
                'clock_out' => ['You have already clocked out today.'],
            ]);
        }

        $attendance->clock_out = now()->format('H:i:s');
        $attendance->save();

        return $attendance;
    }

    protected function validateTokenAndLocation(int $branchId, string $token, float $latitude, float $longitude): void
    {
        if (! $this->qrTokenService->isValidToken($branchId, $token)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid QR code.'],
            ]);
        }

        if (! $this->geofenceService->isWithinBranchGeofence($branchId, $latitude, $longitude)) {
            throw ValidationException::withMessages([
                'location' => ['You must be within the office location to clock in/out.'],
            ]);
        }
    }
}
