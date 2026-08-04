<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse->name),
            'movement_type' => $this->movement_type,
            'quantity' => (float) $this->quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reason' => $this->reason,
            'performed_by' => $this->performed_by,
            'moved_at' => $this->moved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
