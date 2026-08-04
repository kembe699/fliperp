<?php

namespace App\Policies;

use App\Models\MeIndicator;
use App\Models\User;

class MeIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('me-indicators.view');
    }

    public function view(User $user, MeIndicator $indicator): bool
    {
        return $user->can('me-indicators.view') && $indicator->project->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('me-indicators.create');
    }

    public function update(User $user, MeIndicator $indicator): bool
    {
        return $user->can('me-indicators.update') && $indicator->project->company_id === $user->company_id;
    }

    public function delete(User $user, MeIndicator $indicator): bool
    {
        return $user->can('me-indicators.delete') && $indicator->project->company_id === $user->company_id;
    }
}
