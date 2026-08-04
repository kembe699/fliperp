<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeResult extends Model
{
    protected $table = 'me_results';

    protected $fillable = [
        'me_indicator_id',
        'reporting_period',
        'actual_value',
        'notes',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'actual_value' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(MeIndicator::class, 'me_indicator_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
