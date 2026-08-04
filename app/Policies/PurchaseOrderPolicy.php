<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.view') && $purchaseOrder->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.update') && $purchaseOrder->company_id === $user->company_id;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.delete') && $purchaseOrder->company_id === $user->company_id;
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.submit') && $purchaseOrder->company_id === $user->company_id;
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.approve') && $purchaseOrder->company_id === $user->company_id;
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.cancel') && $purchaseOrder->company_id === $user->company_id;
    }
}
