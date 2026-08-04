<?php

namespace App\Policies;

use App\Models\BudgetLine;
use App\Models\User;

class BudgetLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budget-lines.view');
    }

    public function view(User $user, BudgetLine $budgetLine): bool
    {
        return $user->can('budget-lines.view') && $budgetLine->budgetPeriod->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('budget-lines.create');
    }

    public function update(User $user, BudgetLine $budgetLine): bool
    {
        return $user->can('budget-lines.update') && $budgetLine->budgetPeriod->company_id === $user->company_id;
    }

    public function delete(User $user, BudgetLine $budgetLine): bool
    {
        return $user->can('budget-lines.delete') && $budgetLine->budgetPeriod->company_id === $user->company_id;
    }
}
