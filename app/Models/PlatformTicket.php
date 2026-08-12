<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scoped by CompanyScope like any other tenant data (via TenantModel) — a client
 * user querying their own tickets automatically only sees their own company's,
 * exactly like every other tenant-owned model. Platform-admin routes explicitly
 * bypass the scope (Model::withoutGlobalScope(CompanyScope::class)) to see every
 * company's tickets — see PlatformTicketController.
 */
class PlatformTicket extends TenantModel
{
    protected $fillable = [
        'company_id',
        'raised_by_user_id',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
        'source',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * withoutGlobalScope(CompanyScope::class) on both of these: the user on the
     * other end belongs to whichever company raised/is-assigned-the-ticket, which
     * is frequently NOT the same company as whoever is currently querying (a
     * platform-staff user's own company_id is the platform company, but a ticket's
     * raised_by_user_id is almost always a CLIENT company's user) — User applies
     * its own CompanyScope manually (see User::booted()), and without this the
     * relation would silently resolve to null instead of erroring, for every
     * caller whose company_id doesn't happen to match the target user's.
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id')->withoutGlobalScope(CompanyScope::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withoutGlobalScope(CompanyScope::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PlatformTicketReply::class, 'ticket_id')->orderBy('created_at');
    }
}
