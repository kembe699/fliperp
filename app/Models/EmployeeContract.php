<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContract extends Model
{
    protected $fillable = [
        'employee_id',
        'contract_type',
        'start_date',
        'end_date',
        'base_salary',
        'currency_code',
        'document_url',
        'contract_body',
        'status',
        'signed_at',
        'signed_by_name',
        'signature_data',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'base_salary' => 'decimal:2',
            'signed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
