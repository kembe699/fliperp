<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSnapshot extends TenantModel
{
    protected $fillable = [
        'company_id',
        'report_type',
        'period_start',
        'period_end',
        'generated_by',
        'data',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
