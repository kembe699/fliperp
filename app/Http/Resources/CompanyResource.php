<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'client_code' => $this->client_code,
            'logo_url' => $this->logo_url,
            'currency_code' => $this->currency_code,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'is_platform' => $this->is_platform,
            'billing_customer_id' => $this->billing_customer_id,
            'onboarded_by' => $this->onboarded_by,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
