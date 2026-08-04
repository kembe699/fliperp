<?php

namespace App\Models;

class StatutoryDeductionRule extends TenantModel
{
    protected $fillable = [
        'company_id',
        'name',
        'type',
        'calculation_type',
        'config',
        'country_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
