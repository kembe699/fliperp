<?php

namespace App\Policies;

use App\Models\BudgetPeriod;
use App\Models\User;

class BudgetPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budget-periods.view');
    }

    public function view(User $user, BudgetPeriod $budgetPeriod): bool
    {
        return $user->can('budget-periods.view') && $budgetPeriod->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('budget-periods.create');
    }

    public function update(User $user, BudgetPeriod $budgetPeriod): bool
    {
        return $user->can('budget-periods.update') && $budgetPeriod->company_id === $user->company_id;
    }

    public function delete(User $user, BudgetPeriod $budgetPeriod): bool
    {
        return $user->can('budget-periods.delete') && $budgetPeriod->company_id === $user->company_id;
    }
}
