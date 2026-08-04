<?php

namespace App\Policies;

use App\Models\PaymentType;
use App\Models\User;

class PaymentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment-types.view');
    }

    public function view(User $user, PaymentType $paymentType): bool
    {
        return $user->can('payment-types.view') && $paymentType->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('payment-types.create');
    }

    public function update(User $user, PaymentType $paymentType): bool
    {
        return $user->can('payment-types.update') && $paymentType->company_id === $user->company_id;
    }

    public function delete(User $user, PaymentType $paymentType): bool
    {
        return $user->can('payment-types.delete') && $paymentType->company_id === $user->company_id;
    }
}
