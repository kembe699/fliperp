<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('sales.view') && $sale->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->can('sales.update') && $sale->company_id === $user->company_id;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->can('sales.delete') && $sale->company_id === $user->company_id;
    }

    public function hold(User $user, Sale $sale): bool
    {
        return $user->can('sales.hold') && $sale->company_id === $user->company_id;
    }

    public function complete(User $user, Sale $sale): bool
    {
        return $user->can('sales.complete') && $sale->company_id === $user->company_id;
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->can('sales.void') && $sale->company_id === $user->company_id;
    }

    public function refund(User $user, Sale $sale): bool
    {
        return $user->can('sales.refund') && $sale->company_id === $user->company_id;
    }

    public function addPayment(User $user, Sale $sale): bool
    {
        return $user->can('sales.create') && $sale->company_id === $user->company_id;
    }
}
