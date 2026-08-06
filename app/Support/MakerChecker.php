<?php

namespace App\Support;

use App\Models\User;

/**
 * Segregation-of-duties guard: blocks a user from approving/posting their
 * own record only when another user in the same company actually holds the
 * approving permission too. A solo-operator company (one user holding every
 * permission) would otherwise be locked out of approving its own records —
 * self-approval is the only option there, so it stays allowed.
 */
class MakerChecker
{
    public static function blocksSelfApproval(User $actor, ?int $creatorId, string $approvePermission): bool
    {
        if ($creatorId === null || $actor->id !== $creatorId) {
            return false;
        }

        return User::query()
            ->where('company_id', $actor->company_id)
            ->where('id', '!=', $actor->id)
            ->where('is_active', true)
            ->get()
            ->contains(fn (User $other) => $other->can($approvePermission));
    }
}
