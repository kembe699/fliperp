<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll-runs.view');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('payroll-runs.view') && $payrollRun->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('payroll-runs.create');
    }

    public function process(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('payroll-runs.process') && $payrollRun->company_id === $user->company_id;
    }
}
