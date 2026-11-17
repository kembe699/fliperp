<?php

namespace App\Policies;

use App\Models\PaymentApprovalRule;
use App\Models\User;

class PaymentApprovalRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment-approval-rules.view');
    }

    public function manage(User $user, ?PaymentApprovalRule $rule = null): bool
    {
        return $user->can('payment-approval-rules.manage') && (! $rule || $rule->company_id === $user->company_id);
    }
}
