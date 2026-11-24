<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends TenantModel
{
    use Auditable;

    /** Matches the column default, so a just-created account counts as postable. */
    protected $attributes = ['is_postable' => true];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_id',
        'is_active',
        'is_postable',
        'opening_balance',
        'opening_balance_date',
        'opening_balance_entry_id',
    ];

    /** Assets and expenses grow with debits; liabilities, equity and revenue with credits. */
    public const DEBIT_NORMAL_TYPES = ['asset', 'expense'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_postable' => 'boolean',
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }
}
