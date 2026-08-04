<?php

namespace App\Policies;

use App\Models\Dispatch;
use App\Models\User;

class DispatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dispatches.view');
    }

    public function view(User $user, Dispatch $dispatch): bool
    {
        return $user->can('dispatches.view') && $dispatch->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('dispatches.create');
    }

    public function update(User $user, Dispatch $dispatch): bool
    {
        return $user->can('dispatches.update') && $dispatch->company_id === $user->company_id;
    }

    public function delete(User $user, Dispatch $dispatch): bool
    {
        return $user->can('dispatches.delete') && $dispatch->company_id === $user->company_id;
    }

    public function track(User $user, Dispatch $dispatch): bool
    {
        return $user->can('dispatches.track') && $dispatch->company_id === $user->company_id;
    }
}
