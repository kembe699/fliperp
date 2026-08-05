<?php

namespace App\Services\Crm;

use App\Models\CrmService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceCatalogService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmService::query()
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn ($query) => $query->where('is_active', $filters['is_active']))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): CrmService
    {
        return CrmService::create($data);
    }

    public function update(CrmService $service, array $data): CrmService
    {
        $service->update($data);

        return $service;
    }

    public function delete(CrmService $service): void
    {
        $service->delete();
    }
}
