<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $fillable = [
        'budget_period_id',
        'branch_id',
        'department_id',
        'account_id',
        'budgeted_amount',
        'grant_id',
        'project_id',
        'cost_centre_id',
    ];

    protected function casts(): array
    {
        return [
            'budgeted_amount' => 'decimal:2',
        ];
    }

    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeProject::class, 'project_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
