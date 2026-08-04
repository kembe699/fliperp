<?php

namespace App\Policies;

use App\Models\SupplierBill;
use App\Models\User;

class SupplierBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier-bills.view');
    }

    public function view(User $user, SupplierBill $supplierBill): bool
    {
        return $user->can('supplier-bills.view') && $supplierBill->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('supplier-bills.create');
    }

    public function update(User $user, SupplierBill $supplierBill): bool
    {
        return $user->can('supplier-bills.update') && $supplierBill->company_id === $user->company_id;
    }

    public function delete(User $user, SupplierBill $supplierBill): bool
    {
        return $user->can('supplier-bills.delete') && $supplierBill->company_id === $user->company_id;
    }
}
