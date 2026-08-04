<?php

namespace App\Policies;

use App\Models\MeActivity;
use App\Models\User;

class MeActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('me-activities.view');
    }

    public function view(User $user, MeActivity $activity): bool
    {
        return $user->can('me-activities.view') && $activity->project->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('me-activities.create');
    }

    public function update(User $user, MeActivity $activity): bool
    {
        return $user->can('me-activities.update') && $activity->project->company_id === $user->company_id;
    }

    public function delete(User $user, MeActivity $activity): bool
    {
        return $user->can('me-activities.delete') && $activity->project->company_id === $user->company_id;
    }
}
