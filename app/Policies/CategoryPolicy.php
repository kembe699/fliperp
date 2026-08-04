<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.view') && $category->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.update') && $category->company_id === $user->company_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.delete') && $category->company_id === $user->company_id;
    }
}
