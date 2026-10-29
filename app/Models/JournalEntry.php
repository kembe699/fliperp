<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends TenantModel
{
    use Auditable;

    /**
     * Statuses whose lines are part of the ledger. A reversed entry stays in:
     * reverse() posts a mirror-image entry and marks the original
     * 'reversed', so the two together net to zero. Counting only 'posted'
     * dropped the original but kept its mirror — every reversal then showed
     * in the reports as the opposite of the original amount.
     */
    public const LEDGER_STATUSES = ['posted', 'reversed'];

    protected $fillable = [
        'company_id',
        'branch_id',
        'reference_number',
        'entry_date',
        'description',
        'source_module',
        'source_id',
        'posted_by',
        'created_by',
        'status',
        'currency_code',
        'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'exchange_rate' => 'decimal:10',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
