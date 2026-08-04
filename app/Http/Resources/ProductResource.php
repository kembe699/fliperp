<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'unit_of_measure_id' => $this->unit_of_measure_id,
            'cost_price' => (float) $this->cost_price,
            'selling_price' => (float) $this->selling_price,
            'tax_rate_id' => $this->tax_rate_id,
            'reorder_level' => (float) $this->reorder_level,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'track_inventory' => $this->track_inventory,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
