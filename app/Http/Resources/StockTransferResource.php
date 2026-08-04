<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'initiated_by' => $this->initiated_by,
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
