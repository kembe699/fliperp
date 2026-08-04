<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Scopes\CompanyScope;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('attendance.view') && $this->employeeCompanyId($attendance) === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.create');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('attendance.update') && $this->employeeCompanyId($attendance) === $user->company_id;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('attendance.delete') && $this->employeeCompanyId($attendance) === $user->company_id;
    }

    /**
     * Employee's CompanyScope silently filters relation lookups to the
     * current tenant, so $attendance->employee is null (not "another
     * company's employee") once the acting user belongs to a different
     * company — bypass the scope so the comparison below is truthful
     * instead of throwing on a null relation.
     */
    protected function employeeCompanyId(Attendance $attendance): ?int
    {
        return Employee::withoutGlobalScope(CompanyScope::class)->find($attendance->employee_id)?->company_id;
    }
}
