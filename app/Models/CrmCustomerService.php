<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCustomerService extends TenantModel
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'crm_service_id',
        'deal_id',
        'price_charged',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_charged' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CrmService::class, 'crm_service_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }
}
