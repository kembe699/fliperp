<?php

namespace App\Policies;

use App\Models\CashDrawerSession;
use App\Models\User;

class CashDrawerSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cash-drawer-sessions.view');
    }

    public function view(User $user, CashDrawerSession $session): bool
    {
        return $user->can('cash-drawer-sessions.view') && $session->company_id === $user->company_id;
    }

    public function open(User $user): bool
    {
        return $user->can('cash-drawer-sessions.open');
    }

    public function close(User $user, CashDrawerSession $session): bool
    {
        return $user->can('cash-drawer-sessions.close') && $session->company_id === $user->company_id;
    }
}
