<?php

namespace App\Policies;

use App\Models\CrmMeeting;
use App\Models\User;

class CrmMeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-meetings.view');
    }

    public function view(User $user, CrmMeeting $meeting): bool
    {
        return $user->can('crm-meetings.view') && $meeting->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-meetings.create');
    }

    public function update(User $user, CrmMeeting $meeting): bool
    {
        return $user->can('crm-meetings.update') && $meeting->company_id === $user->company_id;
    }

    public function delete(User $user, CrmMeeting $meeting): bool
    {
        return $user->can('crm-meetings.delete') && $meeting->company_id === $user->company_id;
    }
}
