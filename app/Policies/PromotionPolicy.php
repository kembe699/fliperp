<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promotions.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.view') && $promotion->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('promotions.create');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.update') && $promotion->company_id === $user->company_id;
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.delete') && $promotion->company_id === $user->company_id;
    }
}
