<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationSchedule extends Model
{
    protected $fillable = [
        'asset_id',
        'period_date',
        'depreciation_amount',
        'accumulated_depreciation',
        'book_value',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'depreciation_amount' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'book_value' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
