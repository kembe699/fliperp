<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Scopes\CompanyScope;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave-requests.view');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave-requests.view') && $this->employeeCompanyId($leaveRequest) === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('leave-requests.create');
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave-requests.update') && $this->employeeCompanyId($leaveRequest) === $user->company_id;
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave-requests.delete') && $this->employeeCompanyId($leaveRequest) === $user->company_id;
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave-requests.approve') && $this->employeeCompanyId($leaveRequest) === $user->company_id;
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave-requests.reject') && $this->employeeCompanyId($leaveRequest) === $user->company_id;
    }

    /**
     * Employee's CompanyScope silently filters relation lookups to the
     * current tenant, so $leaveRequest->employee is null (not "another
     * company's employee") once the acting user belongs to a different
     * company — bypass the scope so the comparison below is truthful
     * instead of throwing on a null relation.
     */
    protected function employeeCompanyId(LeaveRequest $leaveRequest): ?int
    {
        return Employee::withoutGlobalScope(CompanyScope::class)->find($leaveRequest->employee_id)?->company_id;
    }
}
