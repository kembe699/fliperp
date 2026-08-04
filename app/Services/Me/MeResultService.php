<?php

namespace App\Services\Me;

use App\Models\MeResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MeResultService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return MeResult::query()
            ->whereHas('indicator.project')
            ->when($filters['me_indicator_id'] ?? null, fn ($query, $id) => $query->where('me_indicator_id', $id))
            ->latest('recorded_at')
            ->paginate($perPage);
    }

    public function create(array $data): MeResult
    {
        return MeResult::create([
            'me_indicator_id' => $data['me_indicator_id'],
            'reporting_period' => $data['reporting_period'],
            'actual_value' => $data['actual_value'],
            'notes' => $data['notes'] ?? null,
            'recorded_by' => Auth::id(),
            'recorded_at' => now(),
        ]);
    }

    public function update(MeResult $result, array $data): MeResult
    {
        $result->update($data);

        return $result;
    }

    public function delete(MeResult $result): void
    {
        $result->delete();
    }
}
