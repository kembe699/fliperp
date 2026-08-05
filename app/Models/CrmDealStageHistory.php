<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmDealStageHistory extends Model
{
    protected $table = 'crm_deal_stage_history';

    public $timestamps = true;

    protected $fillable = [
        'deal_id',
        'from_stage_id',
        'to_stage_id',
        'moved_by',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'to_stage_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
