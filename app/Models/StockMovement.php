<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends TenantModel
{
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
