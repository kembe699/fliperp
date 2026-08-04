<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'system_quantity' => (float) $this->system_quantity,
            'counted_quantity' => (float) $this->counted_quantity,
            'variance' => (float) $this->variance,
        ];
    }
}
