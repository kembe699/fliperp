<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variant_id',
        'quantity_ordered',
        'unit_cost',
        'quantity_received',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'quantity_received' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function grnItems(): HasMany
    {
        return $this->hasMany(GrnItem::class);
    }
}
