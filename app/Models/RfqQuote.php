<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A supplier's price for one RFQ item. */
class RfqQuote extends Model
{
    protected $fillable = ['rfq_supplier_id', 'rfq_item_id', 'unit_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }
}
