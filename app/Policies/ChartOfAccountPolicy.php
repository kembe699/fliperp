<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;

class ChartOfAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chart-of-accounts.view');
    }

    public function view(User $user, ChartOfAccount $account): bool
    {
        return $user->can('chart-of-accounts.view') && $account->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('chart-of-accounts.create');
    }

    public function update(User $user, ChartOfAccount $account): bool
    {
        return $user->can('chart-of-accounts.update') && $account->company_id === $user->company_id;
    }

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $user->can('chart-of-accounts.delete') && $account->company_id === $user->company_id;
    }
}
