<?php

namespace App\Policies;

use App\Models\CrmActivity;
use App\Models\User;

class CrmActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-activities.view');
    }

    public function view(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm-activities.view') && $activity->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-activities.create');
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm-activities.update') && $activity->company_id === $user->company_id;
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm-activities.delete') && $activity->company_id === $user->company_id;
    }

    public function resolve(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm-activities.resolve') && $activity->company_id === $user->company_id;
    }
}
