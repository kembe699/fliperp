<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->can('users.view') && $user->company_id === $authUser->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->can('users.update') && $user->company_id === $authUser->company_id;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->can('users.delete') && $user->company_id === $authUser->company_id;
    }
}
