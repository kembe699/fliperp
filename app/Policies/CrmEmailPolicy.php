<?php

namespace App\Policies;

use App\Models\CrmEmail;
use App\Models\User;

class CrmEmailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-emails.view');
    }

    public function view(User $user, CrmEmail $email): bool
    {
        return $user->can('crm-emails.view') && $email->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-emails.create');
    }
}
