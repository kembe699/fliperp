<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DispatchItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_description' => $this->item_description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
        ];
    }
}
