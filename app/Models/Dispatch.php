<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispatch extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_id',
        'reference_number',
        'source_module',
        'source_id',
        'dispatched_by',
        'dispatch_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(DeliveryTracking::class);
    }
}
