<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Several trigger points need "whoever can approve/manage X in this
 * company" rather than one specific user (e.g. a PO awaiting approval, or
 * a branch's cash drawer variance) — this resolves that from the existing
 * Spatie permission set instead of a parallel "who's the manager" concept.
 */
class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function usersWithPermission(int $companyId, string $permission, ?int $branchId = null): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($branchId, fn ($query, $id) => $query->where(fn ($q) => $q->where('branch_id', $id)->orWhereNull('branch_id')))
            ->get()
            ->filter(fn (User $user) => $user->can($permission))
            ->values();
    }
}
