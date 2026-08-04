<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'grn_id',
        'purchase_order_id',
        'reference_number',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'status',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['paid']);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
