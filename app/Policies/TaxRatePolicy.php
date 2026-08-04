<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-rates.view');
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates.view') && $taxRate->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('tax-rates.create');
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates.update') && $taxRate->company_id === $user->company_id;
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates.delete') && $taxRate->company_id === $user->company_id;
    }
}
