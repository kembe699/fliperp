<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveRequestSubmitted extends AppNotification
{
    public function __construct(public LeaveRequest $leaveRequest) {}

    public function toArray(object $notifiable): array
    {
        $employeeName = trim($this->leaveRequest->employee->first_name.' '.$this->leaveRequest->employee->last_name);

        return [
            'title' => 'Leave request submitted',
            'body' => "{$employeeName} requested {$this->leaveRequest->days_count} day(s) of {$this->leaveRequest->leaveType->name}.",
            'link' => '/leave-requests',
            'category' => 'leave_request_submitted',
        ];
    }
}
