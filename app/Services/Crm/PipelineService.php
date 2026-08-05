<?php

namespace App\Services\Crm;

use App\Models\CrmPipelineStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PipelineService
{
    public function all(): Collection
    {
        return CrmPipelineStage::query()->orderBy('position')->get();
    }

    public function create(array $data): CrmPipelineStage
    {
        $data['position'] = $data['position'] ?? ((int) CrmPipelineStage::query()->max('position') + 1);

        return CrmPipelineStage::create($data);
    }

    public function update(CrmPipelineStage $stage, array $data): CrmPipelineStage
    {
        $stage->update($data);

        return $stage;
    }

    public function delete(CrmPipelineStage $stage): void
    {
        $stage->delete();
    }

    /**
     * @param  array<int, int>  $orderedStageIds
     * @return Collection<int, CrmPipelineStage>
     */
    public function reorder(array $orderedStageIds): Collection
    {
        $companyId = Auth::user()->company_id;

        return DB::transaction(function () use ($orderedStageIds, $companyId) {
            foreach ($orderedStageIds as $index => $stageId) {
                CrmPipelineStage::query()
                    ->where('id', $stageId)
                    ->where('company_id', $companyId)
                    ->update(['position' => $index + 1]);
            }

            return $this->all();
        });
    }
}
