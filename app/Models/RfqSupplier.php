<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One supplier asked on an RFQ: when it was sent, and what they quoted. */
class RfqSupplier extends Model
{
    protected $fillable = ['rfq_id', 'supplier_id', 'sent_at', 'sent_to', 'quoted_at', 'delivery_days', 'quote_notes'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'quoted_at' => 'datetime'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RfqQuote::class);
    }
}
