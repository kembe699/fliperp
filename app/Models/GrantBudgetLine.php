<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Donor budget for one account (and optionally one DEA) of a SOF, in the SOF's currency. */
class GrantBudgetLine extends Model
{
    protected $fillable = [
        'grant_id',
        'account_id',
        'dea_id',
        'budgeted_amount',
    ];

    protected function casts(): array
    {
        return [
            'budgeted_amount' => 'decimal:2',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function dea(): BelongsTo
    {
        return $this->belongsTo(GrantDea::class, 'dea_id');
    }
}
