<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.view') && $warehouse->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.update') && $warehouse->company_id === $user->company_id;
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.delete') && $warehouse->company_id === $user->company_id;
    }
}
