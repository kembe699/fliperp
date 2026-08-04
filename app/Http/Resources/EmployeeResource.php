<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'user_id' => $this->user_id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'photo_url' => $this->photo_url,
            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'hire_date' => $this->hire_date?->toDateString(),
            'termination_date' => $this->termination_date?->toDateString(),
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
