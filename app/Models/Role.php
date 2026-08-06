<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Extends Spatie's Role to carry an optional company_id: NULL for the fixed
 * system roles shared across every company, set for a company's own custom
 * role. See the add_company_id_to_roles_table migration for the full
 * rationale.
 */
class Role extends SpatieRole
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isSystemRole(): bool
    {
        return $this->company_id === null;
    }
}
