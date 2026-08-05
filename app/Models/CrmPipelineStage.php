<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipelineStage extends TenantModel
{
    protected $fillable = [
        'company_id',
        'name',
        'position',
        'color',
        'is_closed_won',
        'is_closed_lost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_closed_won' => 'boolean',
            'is_closed_lost' => 'boolean',
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'pipeline_stage_id');
    }

    public function isClosed(): bool
    {
        return $this->is_closed_won || $this->is_closed_lost;
    }
}
