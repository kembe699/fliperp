<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name),
            'product_sku' => $this->whenLoaded('product', fn () => $this->product->sku),
            'reorder_level' => $this->whenLoaded('product', fn () => (float) $this->product->reorder_level),
            'product_variant_id' => $this->product_variant_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'quantity_reserved' => (float) $this->quantity_reserved,
            'is_low_stock' => $this->whenLoaded('product', fn () => (float) $this->quantity_on_hand <= (float) $this->product->reorder_level),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
