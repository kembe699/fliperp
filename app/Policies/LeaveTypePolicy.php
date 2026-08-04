<?php

namespace App\Policies;

use App\Models\LeaveType;
use App\Models\User;

class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave-types.view');
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $user->can('leave-types.view') && $leaveType->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('leave-types.create');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->can('leave-types.update') && $leaveType->company_id === $user->company_id;
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $user->can('leave-types.delete') && $leaveType->company_id === $user->company_id;
    }
}
