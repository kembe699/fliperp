<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'tax_rate_id' => $this->tax_rate_id,
            'discount_amount' => (float) $this->discount_amount,
            'line_total' => (float) $this->line_total,
        ];
    }
}
