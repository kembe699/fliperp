<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentType extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
