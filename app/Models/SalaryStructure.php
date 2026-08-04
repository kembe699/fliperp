<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructure extends Model
{
    protected $fillable = [
        'employee_id',
        'basic_salary',
        'allowances',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances' => 'array',
            'effective_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
