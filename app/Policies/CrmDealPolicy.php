<?php

namespace App\Policies;

use App\Models\CrmDeal;
use App\Models\User;

class CrmDealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-deals.view');
    }

    public function view(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm-deals.view') && $deal->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-deals.create');
    }

    public function update(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm-deals.update') && $deal->company_id === $user->company_id;
    }

    public function delete(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm-deals.delete') && $deal->company_id === $user->company_id;
    }

    public function moveStage(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm-deals.move-stage') && $deal->company_id === $user->company_id;
    }
}
