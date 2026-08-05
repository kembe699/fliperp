<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends TenantModel
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'phone',
        'email',
        'address',
        'tax_id',
        'customer_type',
        'credit_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'converted_customer_id');
    }

    public function crmDeals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    public function crmServices(): HasMany
    {
        return $this->hasMany(CrmCustomerService::class);
    }

    public function crmAccountAssignments(): HasMany
    {
        return $this->hasMany(CrmAccountAssignment::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }
}
