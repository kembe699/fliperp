<?php

namespace App\Policies;

use App\Models\CrmLead;
use App\Models\User;

class CrmLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-leads.view');
    }

    public function view(User $user, CrmLead $lead): bool
    {
        return $user->can('crm-leads.view') && $lead->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-leads.create');
    }

    public function update(User $user, CrmLead $lead): bool
    {
        return $user->can('crm-leads.update') && $lead->company_id === $user->company_id;
    }

    public function delete(User $user, CrmLead $lead): bool
    {
        return $user->can('crm-leads.delete') && $lead->company_id === $user->company_id;
    }

    public function convert(User $user, CrmLead $lead): bool
    {
        return $user->can('crm-leads.convert') && $lead->company_id === $user->company_id;
    }
}
