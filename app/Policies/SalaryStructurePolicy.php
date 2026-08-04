<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Scopes\CompanyScope;
use App\Models\SalaryStructure;
use App\Models\User;

class SalaryStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('salary-structures.view');
    }

    public function view(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('salary-structures.view') && $this->employeeCompanyId($salaryStructure) === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('salary-structures.create');
    }

    public function update(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('salary-structures.update') && $this->employeeCompanyId($salaryStructure) === $user->company_id;
    }

    public function delete(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('salary-structures.delete') && $this->employeeCompanyId($salaryStructure) === $user->company_id;
    }

    /**
     * Employee's CompanyScope silently filters relation lookups to the
     * current tenant, so $salaryStructure->employee is null (not "another
     * company's employee") once the acting user belongs to a different
     * company — bypass the scope so the comparison below is truthful
     * instead of throwing on a null relation.
     */
    protected function employeeCompanyId(SalaryStructure $salaryStructure): ?int
    {
        return Employee::withoutGlobalScope(CompanyScope::class)->find($salaryStructure->employee_id)?->company_id;
    }
}
