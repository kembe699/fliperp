<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'quantity_reserved' => 'decimal:2',
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
}
