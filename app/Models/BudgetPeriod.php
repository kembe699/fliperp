<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPeriod extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
