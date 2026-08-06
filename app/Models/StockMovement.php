<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends TenantModel
{
    // Deliberately not using the Auditable trait here: every write path
    // already goes through StockMovementService, which either logs a
    // precise 'manual_stock_adjustment' audit entry itself (see manual())
    // or is invoked by a parent document (Sale, StockAdjustment,
    // StockTransfer, GRN) that's already comprehensively audited on its own
    // lifecycle. A blanket 'created' hook here would just duplicate that
    // with a less informative raw attribute dump on every single movement.
    // who/when is still captured via performed_by/moved_at below.

    protected $fillable = [
        'company_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'reason',
        'performed_by',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
