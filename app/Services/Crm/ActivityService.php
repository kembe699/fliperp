<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmActivity::query()
            ->with(['customer', 'lead', 'deal', 'loggedBy', 'resolvedBy'])
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($filters['lead_id'] ?? null, fn ($query, $id) => $query->where('lead_id', $id))
            ->when($filters['deal_id'] ?? null, fn ($query, $id) => $query->where('deal_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('activity_date')
            ->paginate($perPage);
    }

    public function create(array $data): CrmActivity
    {
        $data['logged_by'] = Auth::id();
        $data['status'] = $data['status'] ?? 'open';

        return CrmActivity::create($data)->load(['customer', 'lead', 'deal', 'loggedBy']);
    }

    public function update(CrmActivity $activity, array $data): CrmActivity
    {
        $activity->update($data);

        return $activity;
    }

    public function delete(CrmActivity $activity): void
    {
        $activity->delete();
    }

    public function resolve(CrmActivity $activity): CrmActivity
    {
        if ($activity->status === 'resolved') {
            throw ValidationException::withMessages([
                'status' => ['This activity has already been resolved.'],
            ]);
        }

        $activity->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        return $activity->fresh('resolvedBy');
    }
}
