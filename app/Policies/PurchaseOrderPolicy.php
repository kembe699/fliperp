<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\MakerChecker;
use Illuminate\Auth\Access\Response;

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

    public function approve(User $user, PurchaseOrder $purchaseOrder): Response
    {
        if (! $user->can('purchase-orders.approve') || $purchaseOrder->company_id !== $user->company_id) {
            return Response::deny('You do not have permission to approve purchase orders.');
        }

        // Said plainly: a bare 403 left the person who raised the order guessing.
        if (MakerChecker::blocksSelfApproval($user, $purchaseOrder->created_by, 'purchase-orders.approve')) {
            return Response::deny('You raised this purchase order, so another user with approval rights must approve it.');
        }

        return Response::allow();
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.cancel') && $purchaseOrder->company_id === $user->company_id;
    }
}
