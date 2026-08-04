<?php

namespace App\Services\EmployeePortal;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

class LeaveSummaryService
{
    /**
     * Computes entitled/taken/remaining leave days per type for the current
     * calendar year, plus the date of the employee's last approved leave —
     * a derived summary, not a raw dump of leave_requests rows.
     */
    public function summarize(Employee $employee): array
    {
        $year = now()->year;

        $leaveTypes = LeaveType::where('company_id', $employee->company_id)->get();

        $byLeaveType = $leaveTypes->map(function (LeaveType $leaveType) use ($employee, $year) {
            $takenDays = (int) LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->sum('days_count');

            return [
                'leave_type_id' => $leaveType->id,
                'leave_type_name' => $leaveType->name,
                'entitled_days' => $leaveType->days_per_year,
                'taken_days' => $takenDays,
                'remaining_days' => max($leaveType->days_per_year - $takenDays, 0),
            ];
        })->values();

        $lastApproved = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->orderByDesc('end_date')
            ->first();

        return [
            'year' => $year,
            'total_entitled_days' => (int) $byLeaveType->sum('entitled_days'),
            'total_taken_days' => (int) $byLeaveType->sum('taken_days'),
            'total_remaining_days' => (int) $byLeaveType->sum('remaining_days'),
            'last_approved_leave_date' => $lastApproved?->end_date?->toDateString(),
            'by_leave_type' => $byLeaveType,
        ];
    }
}
