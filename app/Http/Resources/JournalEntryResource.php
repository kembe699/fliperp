<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'reference_number' => $this->reference_number,
            'entry_date' => $this->entry_date?->toDateString(),
            'description' => $this->description,
            'source_module' => $this->source_module,
            'source_id' => $this->source_id,
            'posted_by' => $this->posted_by,
            'status' => $this->status,
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'total_debit' => $this->whenLoaded('lines', fn () => (float) $this->lines->sum('debit')),
            'total_credit' => $this->whenLoaded('lines', fn () => (float) $this->lines->sum('credit')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
