<?php

namespace App\Services\Crm;

use App\Models\CrmAccountAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountAssignmentService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmAccountAssignment::query()
            ->with(['customer', 'user'])
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when(array_key_exists('active', $filters) && $filters['active'] !== null, function ($query) use ($filters) {
                $filters['active'] ? $query->active() : $query->whereNotNull('unassigned_at');
            })
            ->latest('assigned_at')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, CrmAccountAssignment>
     */
    public function currentForCustomer(int $customerId): Collection
    {
        return CrmAccountAssignment::query()
            ->with('user')
            ->where('customer_id', $customerId)
            ->active()
            ->get();
    }

    public function assign(array $data): CrmAccountAssignment
    {
        return DB::transaction(function () use ($data) {
            $role = $data['role'] ?? 'primary';

            if ($role === 'primary') {
                CrmAccountAssignment::query()
                    ->where('customer_id', $data['customer_id'])
                    ->where('role', 'primary')
                    ->active()
                    ->update(['unassigned_at' => now()]);
            }

            return CrmAccountAssignment::create([
                'customer_id' => $data['customer_id'],
                'user_id' => $data['user_id'],
                'role' => $role,
                'assigned_at' => now(),
            ])->load('user');
        });
    }

    public function update(CrmAccountAssignment $assignment, array $data): CrmAccountAssignment
    {
        $assignment->update($data);

        return $assignment;
    }

    public function unassign(CrmAccountAssignment $assignment): CrmAccountAssignment
    {
        $assignment->update(['unassigned_at' => now()]);

        return $assignment;
    }

    public function delete(CrmAccountAssignment $assignment): void
    {
        $assignment->delete();
    }
}
