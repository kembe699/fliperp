<?php

namespace App\Policies;

use App\Models\MeProject;
use App\Models\User;

class MeProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('me-projects.view');
    }

    public function view(User $user, MeProject $project): bool
    {
        return $user->can('me-projects.view') && $project->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('me-projects.create');
    }

    public function update(User $user, MeProject $project): bool
    {
        return $user->can('me-projects.update') && $project->company_id === $user->company_id;
    }

    public function delete(User $user, MeProject $project): bool
    {
        return $user->can('me-projects.delete') && $project->company_id === $user->company_id;
    }
}
