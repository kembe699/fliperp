<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'gross_pay' => (float) $this->gross_pay,
            'total_deductions' => (float) $this->total_deductions,
            'net_pay' => (float) $this->net_pay,
            'breakdown' => $this->breakdown,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
