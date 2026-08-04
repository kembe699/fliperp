<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can('departments.view') && $department->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('departments.update') && $department->company_id === $user->company_id;
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('departments.delete') && $department->company_id === $user->company_id;
    }
}
