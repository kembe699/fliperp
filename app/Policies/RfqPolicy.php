<?php

namespace App\Policies;

use App\Models\Rfq;
use App\Models\User;

/** RFQs are part of purchasing: whoever can see / raise purchase orders can see / raise RFQs. */
class RfqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-orders.view');
    }

    public function view(User $user, Rfq $rfq): bool
    {
        return $user->can('purchase-orders.view') && $rfq->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.create');
    }

    /** Edit, send, record quotes, cancel, convert. */
    public function manage(User $user, Rfq $rfq): bool
    {
        return $user->can('purchase-orders.create') && $rfq->company_id === $user->company_id;
    }
}
