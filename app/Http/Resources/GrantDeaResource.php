<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantDeaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grant_id' => $this->grant_id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
