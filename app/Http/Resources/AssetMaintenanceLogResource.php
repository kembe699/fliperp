<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMaintenanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'maintenance_date' => $this->maintenance_date?->toDateString(),
            'description' => $this->description,
            'cost' => (float) $this->cost,
            'performed_by' => $this->performed_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
