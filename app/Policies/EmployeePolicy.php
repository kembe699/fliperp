<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employees.view') && $employee->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.update') && $employee->company_id === $user->company_id;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete') && $employee->company_id === $user->company_id;
    }
}
