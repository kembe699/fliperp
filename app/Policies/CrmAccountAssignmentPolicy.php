<?php

namespace App\Policies;

use App\Models\CrmAccountAssignment;
use App\Models\User;

class CrmAccountAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-account-assignments.view');
    }

    public function view(User $user, CrmAccountAssignment $assignment): bool
    {
        return $user->can('crm-account-assignments.view') && $assignment->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-account-assignments.assign');
    }

    public function update(User $user, CrmAccountAssignment $assignment): bool
    {
        return $user->can('crm-account-assignments.assign') && $assignment->company_id === $user->company_id;
    }

    public function delete(User $user, CrmAccountAssignment $assignment): bool
    {
        return $user->can('crm-account-assignments.unassign') && $assignment->company_id === $user->company_id;
    }

    public function unassign(User $user, CrmAccountAssignment $assignment): bool
    {
        return $user->can('crm-account-assignments.unassign') && $assignment->company_id === $user->company_id;
    }
}
