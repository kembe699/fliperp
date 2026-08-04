<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Scopes\CompanyScope;
use App\Models\User;

class EmployeeContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employee-contracts.view');
    }

    public function view(User $user, EmployeeContract $contract): bool
    {
        return $user->can('employee-contracts.view') && $this->employeeCompanyId($contract) === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('employee-contracts.create');
    }

    public function update(User $user, EmployeeContract $contract): bool
    {
        return $user->can('employee-contracts.update') && $this->employeeCompanyId($contract) === $user->company_id;
    }

    public function delete(User $user, EmployeeContract $contract): bool
    {
        return $user->can('employee-contracts.delete') && $this->employeeCompanyId($contract) === $user->company_id;
    }

    /**
     * Employee's CompanyScope silently filters relation lookups to the
     * current tenant, so $contract->employee is null (not "another
     * company's employee") once the acting user belongs to a different
     * company — bypass the scope so the comparison below is truthful
     * instead of throwing on a null relation.
     */
    protected function employeeCompanyId(EmployeeContract $contract): ?int
    {
        return Employee::withoutGlobalScope(CompanyScope::class)->find($contract->employee_id)?->company_id;
    }
}
