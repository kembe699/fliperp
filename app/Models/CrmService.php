<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmService extends TenantModel
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'category',
        'default_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'crm_service_id');
    }

    public function customerServices(): HasMany
    {
        return $this->hasMany(CrmCustomerService::class, 'crm_service_id');
    }
}
