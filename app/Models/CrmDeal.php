<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmDeal extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'lead_id',
        'customer_id',
        'pipeline_stage_id',
        'crm_service_id',
        'title',
        'value',
        'expected_close_date',
        'assigned_to',
        'closed_at',
        'lost_reason',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'pipeline_stage_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CrmService::class, 'crm_service_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(CrmDealStageHistory::class, 'deal_id')->latest('moved_at');
    }

    public function customerServices(): HasMany
    {
        return $this->hasMany(CrmCustomerService::class, 'deal_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'deal_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(CrmService::class, 'crm_deal_services', 'deal_id', 'crm_service_id')->withTimestamps();
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(CrmMeeting::class, 'deal_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CrmEmail::class, 'deal_id');
    }

    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(Quotation::class, 'crm_deal_quotations', 'deal_id', 'quotation_id')->withTimestamps();
    }
}
