<?php

namespace App\Services\Hr;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Notifications\LeaveRequestDecided;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\Notifications\NotificationRecipientResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(protected NotificationRecipientResolver $recipientResolver) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveRequest::query()
            ->whereHas('employee')
            ->with(['employee', 'leaveType'])
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): LeaveRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $daysCount = $start->diffInDays($end) + 1;

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        $this->assertWithinBalance($data['employee_id'], $leaveType, $start->year, $daysCount);

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_count' => $daysCount,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        $approvers = $this->recipientResolver->usersWithPermission(
            $leaveRequest->employee->company_id,
            'leave-requests.approve',
            $leaveRequest->employee->branch_id,
        )->reject(fn ($user) => $user->id === Auth::id());
        Notification::send($approvers, new LeaveRequestSubmitted($leaveRequest));

        return $leaveRequest;
    }

    public function update(LeaveRequest $leaveRequest, array $data): LeaveRequest
    {
        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be edited.'],
            ]);
        }

        $start = Carbon::parse($data['start_date'] ?? $leaveRequest->start_date);
        $end = Carbon::parse($data['end_date'] ?? $leaveRequest->end_date);
        $daysCount = $start->diffInDays($end) + 1;

        $leaveRequest->update([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days_count' => $daysCount,
            'reason' => $data['reason'] ?? $leaveRequest->reason,
        ]);

        return $leaveRequest;
    }

    public function delete(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->delete();
    }

    public function approve(LeaveRequest $leaveRequest): LeaveRequest
    {
        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be approved.'],
            ]);
        }

        $leaveType = $leaveRequest->leaveType;

        $this->assertWithinBalance(
            $leaveRequest->employee_id,
            $leaveType,
            Carbon::parse($leaveRequest->start_date)->year,
            $leaveRequest->days_count,
            excludeId: $leaveRequest->id,
        );

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ]);

        $this->notifyDecision($leaveRequest);

        return $leaveRequest;
    }

    public function reject(LeaveRequest $leaveRequest): LeaveRequest
    {
        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be rejected.'],
            ]);
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        $this->notifyDecision($leaveRequest);

        return $leaveRequest;
    }

    protected function notifyDecision(LeaveRequest $leaveRequest): void
    {
        $employeeUser = $leaveRequest->employee->user;

        if ($employeeUser) {
            $employeeUser->notify(new LeaveRequestDecided($leaveRequest));
        }
    }

    protected function assertWithinBalance(int $employeeId, LeaveType $leaveType, int $year, int $daysCount, ?int $excludeId = null): void
    {
        $alreadyApproved = LeaveRequest::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->when($excludeId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->sum('days_count');

        if ($alreadyApproved + $daysCount > $leaveType->days_per_year) {
            $remaining = max($leaveType->days_per_year - $alreadyApproved, 0);

            throw ValidationException::withMessages([
                'days_count' => ["This request exceeds the employee's remaining {$leaveType->name} balance ({$remaining} day(s) left)."],
            ]);
        }
    }
}
