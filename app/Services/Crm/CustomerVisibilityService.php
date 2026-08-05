<?php

namespace App\Services\Crm;

use App\Models\CrmAccountAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gates CRM-specific customer data (services, activities, statements) by
 * whether the acting user holds crm-customers.view-all or is instead
 * restricted to only the customers they're actively assigned to via
 * crm_account_assignments (role primary or support, unassigned_at null).
 *
 * This deliberately does NOT touch the base Sales/POS customer list
 * (CustomerController/CustomerPolicy) — a cashier or branch manager still
 * needs to see and select every company customer at checkout regardless
 * of CRM ownership. Only the new crm/customers/* endpoints and other
 * CRM-specific reads apply this restriction.
 */
class CustomerVisibilityService
{
    public function canViewAll(User $user): bool
    {
        return $user->can('crm-customers.view-all');
    }

    /**
     * @return array<int, int>
     */
    public function assignedCustomerIds(User $user): array
    {
        return CrmAccountAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->pluck('customer_id')
            ->all();
    }

    public function canAccessCustomer(User $user, int $customerId): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        return CrmAccountAssignment::query()
            ->where('user_id', $user->id)
            ->where('customer_id', $customerId)
            ->active()
            ->exists();
    }

    /**
     * Restricts a query to only the given user's assigned customers unless
     * they hold crm-customers.view-all, in which case it's left untouched.
     */
    public function scopeToVisibleCustomers(Builder $query, User $user, string $customerIdColumn = 'id'): Builder
    {
        if ($this->canViewAll($user)) {
            return $query;
        }

        return $query->whereIn($customerIdColumn, $this->assignedCustomerIds($user));
    }
}
