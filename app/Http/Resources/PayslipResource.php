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
            'employee_name' => $this->whenLoaded('employee', fn () => trim($this->employee->first_name.' '.$this->employee->last_name)),
            'employee_code' => $this->whenLoaded('employee', fn () => $this->employee->employee_code),
            'department' => $this->whenLoaded('employee', fn () => $this->employee->relationLoaded('department') ? $this->employee->department?->name : null),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_by_name' => $this->whenLoaded('paidBy', fn () => $this->paidBy?->name),
            'payment_type_name' => $this->whenLoaded('paymentType', fn () => $this->paymentType?->name),
            'payment_account_name' => $this->whenLoaded('paymentAccount', fn () => $this->paymentAccount ? $this->paymentAccount->code.' '.$this->paymentAccount->name : null),
            'payment_reference' => $this->payment_reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
