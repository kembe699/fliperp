<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
