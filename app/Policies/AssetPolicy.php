<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('assets.view') && $asset->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('assets.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('assets.update') && $asset->company_id === $user->company_id;
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('assets.delete') && $asset->company_id === $user->company_id;
    }

    public function assign(User $user, Asset $asset): bool
    {
        return $user->can('assets.assign') && $asset->company_id === $user->company_id;
    }

    public function dispose(User $user, Asset $asset): bool
    {
        return $user->can('assets.dispose') && $asset->company_id === $user->company_id;
    }

    public function runDepreciation(User $user): bool
    {
        return $user->can('assets.run-depreciation');
    }
}
