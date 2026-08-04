<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'me_indicator_id' => $this->me_indicator_id,
            'reporting_period' => $this->reporting_period,
            'actual_value' => (float) $this->actual_value,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
