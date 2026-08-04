<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'quantity_received' => (float) $this->quantity_received,
            'unit_cost' => (float) $this->unit_cost,
            'condition' => $this->condition,
        ];
    }
}
