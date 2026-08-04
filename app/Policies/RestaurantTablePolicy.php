<?php

namespace App\Policies;

use App\Models\RestaurantTable;
use App\Models\User;

class RestaurantTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('restaurant-tables.view');
    }

    public function view(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can('restaurant-tables.view') && $restaurantTable->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('restaurant-tables.create');
    }

    public function update(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can('restaurant-tables.update') && $restaurantTable->company_id === $user->company_id;
    }

    public function delete(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can('restaurant-tables.delete') && $restaurantTable->company_id === $user->company_id;
    }
}
