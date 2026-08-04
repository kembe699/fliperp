<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'asset_category_id' => $this->asset_category_id,
            'asset_code' => $this->asset_code,
            'name' => $this->name,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => (float) $this->purchase_cost,
            'current_value' => (float) $this->current_value,
            'status' => $this->status,
            'assigned_to_employee_id' => $this->assigned_to_employee_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
