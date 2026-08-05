<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmPipelineStage\ReorderCrmPipelineStagesRequest;
use App\Http\Requests\CrmPipelineStage\StoreCrmPipelineStageRequest;
use App\Http\Requests\CrmPipelineStage\UpdateCrmPipelineStageRequest;
use App\Http\Resources\CrmPipelineStageResource;
use App\Models\CrmPipelineStage;
use App\Services\Crm\PipelineService;
use Illuminate\Http\JsonResponse;

class CrmPipelineStageController extends Controller
{
    public function __construct(protected PipelineService $pipelineService) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', CrmPipelineStage::class);

        return $this->success(CrmPipelineStageResource::collection($this->pipelineService->all()));
    }

    public function store(StoreCrmPipelineStageRequest $request): JsonResponse
    {
        $this->authorize('create', CrmPipelineStage::class);

        $stage = $this->pipelineService->create($request->validated());

        return $this->success(new CrmPipelineStageResource($stage), 'Pipeline stage created successfully.', 201);
    }

    public function show(CrmPipelineStage $crmPipelineStage): JsonResponse
    {
        $this->authorize('view', $crmPipelineStage);

        return $this->success(new CrmPipelineStageResource($crmPipelineStage));
    }

    public function update(UpdateCrmPipelineStageRequest $request, CrmPipelineStage $crmPipelineStage): JsonResponse
    {
        $this->authorize('update', $crmPipelineStage);

        $stage = $this->pipelineService->update($crmPipelineStage, $request->validated());

        return $this->success(new CrmPipelineStageResource($stage), 'Pipeline stage updated successfully.');
    }

    public function destroy(CrmPipelineStage $crmPipelineStage): JsonResponse
    {
        $this->authorize('delete', $crmPipelineStage);

        $this->pipelineService->delete($crmPipelineStage);

        return $this->success(null, 'Pipeline stage deleted successfully.');
    }

    public function reorder(ReorderCrmPipelineStagesRequest $request): JsonResponse
    {
        $this->authorize('reorder', CrmPipelineStage::class);

        $stages = $this->pipelineService->reorder($request->validated('stage_ids'));

        return $this->success(CrmPipelineStageResource::collection($stages), 'Pipeline stages reordered successfully.');
    }
}
