<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('positions.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->can('positions.view') && $position->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('positions.create');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->can('positions.update') && $position->company_id === $user->company_id;
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->can('positions.delete') && $position->company_id === $user->company_id;
    }
}
