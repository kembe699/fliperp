<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vehicles.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.view') && $vehicle->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('vehicles.create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.update') && $vehicle->company_id === $user->company_id;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.delete') && $vehicle->company_id === $user->company_id;
    }
}
