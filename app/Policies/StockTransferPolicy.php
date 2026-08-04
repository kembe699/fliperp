<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock-transfers.view');
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.view') && $stockTransfer->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('stock-transfers.create');
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.update') && $stockTransfer->company_id === $user->company_id;
    }

    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.delete') && $stockTransfer->company_id === $user->company_id;
    }

    public function complete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.complete') && $stockTransfer->company_id === $user->company_id;
    }
}
