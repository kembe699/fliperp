<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'contract_type' => $this->contract_type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'base_salary' => (float) $this->base_salary,
            'currency_code' => $this->currency_code,
            'document_url' => $this->document_url,
            'contract_body' => $this->contract_body,
            'status' => $this->status,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_by_name' => $this->signed_by_name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
