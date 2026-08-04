<?php

namespace App\Services\Me;

use App\Models\MeActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MeActivityService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return MeActivity::query()
            ->whereHas('project')
            ->when($filters['me_project_id'] ?? null, fn ($query, $id) => $query->where('me_project_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): MeActivity
    {
        return MeActivity::create($data);
    }

    public function update(MeActivity $activity, array $data): MeActivity
    {
        $activity->update($data);

        return $activity;
    }

    public function delete(MeActivity $activity): void
    {
        $activity->delete();
    }
}
