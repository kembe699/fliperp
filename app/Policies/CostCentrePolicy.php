<?php

namespace App\Policies;

use App\Models\CostCentre;
use App\Models\User;

class CostCentrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cost-centres.view');
    }

    public function view(User $user, CostCentre $costCentre): bool
    {
        return $user->can('cost-centres.view') && $costCentre->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('cost-centres.create');
    }

    public function update(User $user, CostCentre $costCentre): bool
    {
        return $user->can('cost-centres.update') && $costCentre->company_id === $user->company_id;
    }

    public function delete(User $user, CostCentre $costCentre): bool
    {
        return $user->can('cost-centres.delete') && $costCentre->company_id === $user->company_id;
    }
}
