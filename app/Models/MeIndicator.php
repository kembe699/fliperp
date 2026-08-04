<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeIndicator extends Model
{
    protected $table = 'me_indicators';

    protected $fillable = [
        'me_project_id',
        'name',
        'unit_of_measure',
        'target_value',
        'baseline_value',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'baseline_value' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeProject::class, 'me_project_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(MeResult::class, 'me_indicator_id');
    }
}
