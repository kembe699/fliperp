<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeProject extends TenantModel
{
    use Auditable;

    protected $table = 'me_projects';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'budget_period_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(MeIndicator::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(MeActivity::class);
    }
}
