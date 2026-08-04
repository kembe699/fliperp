<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceQrToken extends TenantModel
{
    // The migration only defines created_at (no updated_at) — this token
    // row is never updated in place, only superseded by a new one on
    // regenerate, so Eloquent's automatic timestamp pair doesn't apply.
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'branch_id',
        'token',
        'is_active',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
