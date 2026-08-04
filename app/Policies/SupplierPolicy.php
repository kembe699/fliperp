<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.view') && $supplier->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.update') && $supplier->company_id === $user->company_id;
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.delete') && $supplier->company_id === $user->company_id;
    }
}
