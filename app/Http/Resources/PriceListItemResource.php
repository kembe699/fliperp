<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_list_id' => $this->price_list_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'price' => (float) $this->price,
        ];
    }
}
