<?php

namespace App\Models\Concerns;

use App\Models\CostCentre;
use App\Models\Grant;
use App\Models\GrantDea;
use App\Models\MeProject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The coding block on a line: SOF (grant), DEA, project and cost centre, each
 * optional. The models using it list the four ids in $fillable themselves.
 */
trait HasDimensions
{
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }

    public function dea(): BelongsTo
    {
        return $this->belongsTo(GrantDea::class, 'dea_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeProject::class, 'project_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
