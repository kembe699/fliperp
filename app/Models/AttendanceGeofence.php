<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceGeofence extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
