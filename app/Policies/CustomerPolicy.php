<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $customer->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $customer->company_id === $user->company_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete') && $customer->company_id === $user->company_id;
    }
}
