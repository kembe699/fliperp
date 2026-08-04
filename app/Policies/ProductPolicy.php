<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view') && $product->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update') && $product->company_id === $user->company_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete') && $product->company_id === $user->company_id;
    }
}
