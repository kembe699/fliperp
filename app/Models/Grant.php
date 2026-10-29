<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SOF (Source of Funding): one donor award, with its own currency (the one
 * the donor is reported to in, usually USD), its own dates — lines can only be
 * coded to it within them — and a budget in that currency.
 */
class Grant extends TenantModel
{
    use Auditable;

    public const STATUSES = ['active', 'closed'];

    protected $fillable = [
        'company_id',
        'donor_id',
        'code',
        'name',
        'currency_code',
        'start_date',
        'end_date',
        'budget_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget_amount' => 'decimal:2',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function deas(): HasMany
    {
        return $this->hasMany(GrantDea::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(GrantBudgetLine::class);
    }
}
