<?php

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()->orderBy('sort_order')->latest()->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function delete(Category $category): void
    {
        if (Product::where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Cannot delete a category that still has products. Deactivate it instead.'],
            ]);
        }

        $category->delete();
    }
}
