<?php

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;

class AccountingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting-periods.view');
    }

    public function view(User $user, AccountingPeriod $period): bool
    {
        return $user->can('accounting-periods.view') && $period->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('accounting-periods.create');
    }

    public function update(User $user, AccountingPeriod $period): bool
    {
        return $user->can('accounting-periods.update') && $period->company_id === $user->company_id;
    }

    public function delete(User $user, AccountingPeriod $period): bool
    {
        return $user->can('accounting-periods.delete') && $period->company_id === $user->company_id;
    }

    public function close(User $user, AccountingPeriod $period): bool
    {
        return $user->can('accounting-periods.close') && $period->company_id === $user->company_id;
    }
}
