<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'days_per_year',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
