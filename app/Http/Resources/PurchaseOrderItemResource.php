<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\DimensionFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    use DimensionFields;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity_ordered' => (float) $this->quantity_ordered,
            'unit_cost' => (float) $this->unit_cost,
            'quantity_received' => (float) $this->quantity_received,
            ...$this->dimensionFields(),
        ];
    }
}
