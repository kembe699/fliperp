<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'payment_type_id' => $this->payment_type_id,
            'amount' => (float) $this->amount,
            'reference_number' => $this->reference_number,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
