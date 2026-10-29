<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A DEA (Detailed Expenditure Activity): a budget line code within one SOF. */
class GrantDea extends TenantModel
{
    use Auditable;

    protected $table = 'grant_deas';

    protected $fillable = [
        'company_id',
        'grant_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }
}
