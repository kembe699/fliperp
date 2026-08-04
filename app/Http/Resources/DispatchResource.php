<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DispatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'vehicle_id' => $this->vehicle_id,
            'reference_number' => $this->reference_number,
            'source_module' => $this->source_module,
            'source_id' => $this->source_id,
            'dispatched_by' => $this->dispatched_by,
            'dispatch_date' => $this->dispatch_date?->toDateString(),
            'status' => $this->status,
            'items' => DispatchItemResource::collection($this->whenLoaded('items')),
            'tracking' => DeliveryTrackingResource::collection($this->whenLoaded('tracking')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
