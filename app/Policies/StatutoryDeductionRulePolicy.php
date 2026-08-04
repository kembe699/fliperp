<?php

namespace App\Policies;

use App\Models\StatutoryDeductionRule;
use App\Models\User;

class StatutoryDeductionRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('statutory-deduction-rules.view');
    }

    public function view(User $user, StatutoryDeductionRule $rule): bool
    {
        return $user->can('statutory-deduction-rules.view') && $rule->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('statutory-deduction-rules.create');
    }

    public function update(User $user, StatutoryDeductionRule $rule): bool
    {
        return $user->can('statutory-deduction-rules.update') && $rule->company_id === $user->company_id;
    }

    public function delete(User $user, StatutoryDeductionRule $rule): bool
    {
        return $user->can('statutory-deduction-rules.delete') && $rule->company_id === $user->company_id;
    }
}
