<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends TenantModel
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_category_id',
        'asset_code',
        'name',
        'purchase_date',
        'purchase_cost',
        'current_value',
        'status',
        'assigned_to_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'current_value' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to_employee_id');
    }

    public function depreciationSchedules(): HasMany
    {
        return $this->hasMany(AssetDepreciationSchedule::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class);
    }
}
