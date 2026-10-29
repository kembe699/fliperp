<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

class CostCentre extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
