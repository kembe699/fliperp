<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveRequestDecided extends AppNotification
{
    public function __construct(public LeaveRequest $leaveRequest) {}

    public function toArray(object $notifiable): array
    {
        $decision = $this->leaveRequest->status === 'approved' ? 'approved' : 'rejected';

        return [
            'title' => "Leave request {$decision}",
            'body' => "Your {$this->leaveRequest->leaveType->name} request for {$this->leaveRequest->days_count} day(s) was {$decision}.",
            'link' => '/leave-requests',
            'category' => 'leave_request_decided',
        ];
    }
}
