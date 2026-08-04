<?php

namespace App\Services\Me;

use App\Models\MeIndicator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MeIndicatorService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return MeIndicator::query()
            ->whereHas('project')
            ->when($filters['me_project_id'] ?? null, fn ($query, $id) => $query->where('me_project_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): MeIndicator
    {
        return MeIndicator::create($data);
    }

    public function update(MeIndicator $indicator, array $data): MeIndicator
    {
        $indicator->update($data);

        return $indicator;
    }

    public function delete(MeIndicator $indicator): void
    {
        $indicator->delete();
    }
}
