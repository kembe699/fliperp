<?php

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;

class ExchangeRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exchange-rates.view');
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->can('exchange-rates.view') && $exchangeRate->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('exchange-rates.create');
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->can('exchange-rates.update') && $exchangeRate->company_id === $user->company_id;
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->can('exchange-rates.delete') && $exchangeRate->company_id === $user->company_id;
    }
}
