<?php

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customer-payments.view');
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.view') && $payment->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('customer-payments.create');
    }

    public function update(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.update') && $payment->company_id === $user->company_id;
    }

    public function delete(User $user, CustomerPayment $payment): bool
    {
        return $user->can('customer-payments.delete') && $payment->company_id === $user->company_id;
    }
}
