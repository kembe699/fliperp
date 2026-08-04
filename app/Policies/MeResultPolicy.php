<?php

namespace App\Policies;

use App\Models\MeResult;
use App\Models\User;

class MeResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('me-results.view');
    }

    public function view(User $user, MeResult $result): bool
    {
        return $user->can('me-results.view') && $result->indicator->project->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('me-results.create');
    }

    public function update(User $user, MeResult $result): bool
    {
        return $user->can('me-results.update') && $result->indicator->project->company_id === $user->company_id;
    }

    public function delete(User $user, MeResult $result): bool
    {
        return $user->can('me-results.delete') && $result->indicator->project->company_id === $user->company_id;
    }
}
