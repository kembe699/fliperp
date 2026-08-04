<?php

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;

class UnitOfMeasurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units-of-measure.view');
    }

    public function view(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('units-of-measure.view') && $unitOfMeasure->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('units-of-measure.create');
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('units-of-measure.update') && $unitOfMeasure->company_id === $user->company_id;
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can('units-of-measure.delete') && $unitOfMeasure->company_id === $user->company_id;
    }
}
