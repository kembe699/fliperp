<?php

namespace App\Http\Resources\Concerns;

/**
 * The coding block (SOF, DEA, project, cost centre) of a line, as ids plus
 * the codes people read them by. Only the dimensions a line is coded to are
 * looked up.
 */
trait DimensionFields
{
    protected function dimensionFields(): array
    {
        return [
            'grant_id' => $this->grant_id,
            'grant_code' => $this->grant_id ? $this->grant?->code : null,
            'dea_id' => $this->dea_id,
            'dea_code' => $this->dea_id ? $this->dea?->code : null,
            'project_id' => $this->project_id,
            'project_code' => $this->project_id ? ($this->project?->code ?: $this->project?->name) : null,
            'cost_centre_id' => $this->cost_centre_id,
            'cost_centre_code' => $this->cost_centre_id ? $this->costCentre?->code : null,
        ];
    }
}
