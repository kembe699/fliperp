<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
            ] : null),
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'pipeline_stage' => $this->whenLoaded('pipelineStage', fn () => $this->pipelineStage ? new CrmPipelineStageResource($this->pipelineStage) : null),
            'crm_service_id' => $this->crm_service_id,
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'title' => $this->title,
            'value' => (float) $this->value,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
