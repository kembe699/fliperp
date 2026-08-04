<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrnItem extends Model
{
    protected $fillable = [
        'grn_id',
        'product_id',
        'product_variant_id',
        'purchase_order_item_id',
        'quantity_received',
        'unit_cost',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
