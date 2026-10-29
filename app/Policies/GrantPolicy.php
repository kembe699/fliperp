<?php

namespace App\Policies;

use App\Models\Grant;
use App\Models\User;

class GrantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('grants.view');
    }

    public function view(User $user, Grant $grant): bool
    {
        return $user->can('grants.view') && $grant->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('grants.create');
    }

    public function update(User $user, Grant $grant): bool
    {
        return $user->can('grants.update') && $grant->company_id === $user->company_id;
    }

    public function delete(User $user, Grant $grant): bool
    {
        return $user->can('grants.delete') && $grant->company_id === $user->company_id;
    }
}
