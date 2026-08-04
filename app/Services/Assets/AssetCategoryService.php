<?php

namespace App\Services\Assets;

use App\Models\AssetCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetCategoryService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return AssetCategory::query()->latest()->paginate($perPage);
    }

    public function create(array $data): AssetCategory
    {
        return AssetCategory::create($data);
    }

    public function update(AssetCategory $category, array $data): AssetCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(AssetCategory $category): void
    {
        $category->delete();
    }
}
