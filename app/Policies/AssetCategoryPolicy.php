<?php

namespace App\Policies;

use App\Models\AssetCategory;
use App\Models\User;

class AssetCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('asset-categories.view');
    }

    public function view(User $user, AssetCategory $category): bool
    {
        return $user->can('asset-categories.view') && $category->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('asset-categories.create');
    }

    public function update(User $user, AssetCategory $category): bool
    {
        return $user->can('asset-categories.update') && $category->company_id === $user->company_id;
    }

    public function delete(User $user, AssetCategory $category): bool
    {
        return $user->can('asset-categories.delete') && $category->company_id === $user->company_id;
    }
}
