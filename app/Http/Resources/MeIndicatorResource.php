<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeIndicatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'me_project_id' => $this->me_project_id,
            'name' => $this->name,
            'unit_of_measure' => $this->unit_of_measure,
            'target_value' => (float) $this->target_value,
            'baseline_value' => (float) $this->baseline_value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
