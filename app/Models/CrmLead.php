<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'company_name',
        'email',
        'phone',
        'source',
        'status',
        'converted_customer_id',
        'assigned_to',
        'notes',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(CrmService::class, 'crm_lead_services', 'lead_id', 'crm_service_id')->withTimestamps();
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(CrmMeeting::class, 'lead_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CrmEmail::class, 'lead_id');
    }

    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(Quotation::class, 'crm_lead_quotations', 'lead_id', 'quotation_id')->withTimestamps();
    }
}
