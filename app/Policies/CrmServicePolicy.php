<?php

namespace App\Policies;

use App\Models\CrmService;
use App\Models\User;

class CrmServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-services.view');
    }

    public function view(User $user, CrmService $service): bool
    {
        return $user->can('crm-services.view') && $service->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-services.create');
    }

    public function update(User $user, CrmService $service): bool
    {
        return $user->can('crm-services.update') && $service->company_id === $user->company_id;
    }

    public function delete(User $user, CrmService $service): bool
    {
        return $user->can('crm-services.delete') && $service->company_id === $user->company_id;
    }
}
