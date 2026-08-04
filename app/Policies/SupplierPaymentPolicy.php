<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier-payments.view');
    }

    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('supplier-payments.view') && $supplierPayment->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('supplier-payments.create');
    }

    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('supplier-payments.update') && $supplierPayment->company_id === $user->company_id;
    }

    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('supplier-payments.delete') && $supplierPayment->company_id === $user->company_id;
    }
}
