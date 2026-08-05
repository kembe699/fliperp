<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Services\Crm\CustomerVisibilityService;

class CustomerPolicy
{
    public function __construct(protected CustomerVisibilityService $customerVisibilityService) {}

    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $customer->company_id === $user->company_id;
    }

    /**
     * Gates the CRM-specific customer endpoints (crm/customers/*,
     * service-statement, customer-scoped activities) — distinct from
     * view()/viewAny() above, which continue to gate the general Sales/POS
     * customer list unrestricted by CRM account assignment.
     */
    public function viewAnyInCrm(User $user): bool
    {
        return $user->can('crm-customer-services.view');
    }

    public function viewInCrm(User $user, Customer $customer): bool
    {
        return $user->can('crm-customer-services.view')
            && $customer->company_id === $user->company_id
            && $this->customerVisibilityService->canAccessCustomer($user, $customer->id);
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $customer->company_id === $user->company_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete') && $customer->company_id === $user->company_id;
    }
}
