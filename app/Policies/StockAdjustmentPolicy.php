<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\MakerChecker;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock-adjustments.view');
    }

    public function view(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.view') && $stockAdjustment->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('stock-adjustments.create');
    }

    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.update') && $stockAdjustment->company_id === $user->company_id;
    }

    public function delete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.delete') && $stockAdjustment->company_id === $user->company_id;
    }

    public function approve(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.approve')
            && $stockAdjustment->company_id === $user->company_id
            && ! MakerChecker::blocksSelfApproval($user, $stockAdjustment->adjusted_by, 'stock-adjustments.approve');
    }
}
