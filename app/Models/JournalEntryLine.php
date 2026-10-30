<?php

namespace App\Models;

use App\Models\Concerns\HasDimensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasDimensions;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'transaction_debit',
        'transaction_credit',
        'description',
        'grant_id',
        'dea_id',
        'project_id',
        'cost_centre_id',
        'department_id',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'transaction_debit' => 'decimal:2',
            'transaction_credit' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
