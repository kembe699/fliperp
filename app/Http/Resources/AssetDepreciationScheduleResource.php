<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetDepreciationScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'period_date' => $this->period_date?->toDateString(),
            'depreciation_amount' => (float) $this->depreciation_amount,
            'accumulated_depreciation' => (float) $this->accumulated_depreciation,
            'book_value' => (float) $this->book_value,
            'journal_entry_id' => $this->journal_entry_id,
        ];
    }
}
