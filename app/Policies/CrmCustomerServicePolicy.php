<?php

namespace App\Policies;

use App\Models\CrmCustomerService;
use App\Models\User;

class CrmCustomerServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm-customer-services.view');
    }

    public function view(User $user, CrmCustomerService $customerService): bool
    {
        return $user->can('crm-customer-services.view') && $customerService->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm-customer-services.create');
    }

    public function update(User $user, CrmCustomerService $customerService): bool
    {
        return $user->can('crm-customer-services.update') && $customerService->company_id === $user->company_id;
    }

    public function delete(User $user, CrmCustomerService $customerService): bool
    {
        return $user->can('crm-customer-services.delete') && $customerService->company_id === $user->company_id;
    }
}
