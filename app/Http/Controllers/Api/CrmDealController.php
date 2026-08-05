<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmDeal\MoveCrmDealStageRequest;
use App\Http\Requests\CrmDeal\StoreCrmDealRequest;
use App\Http\Requests\CrmDeal\UpdateCrmDealRequest;
use App\Http\Resources\CrmDealResource;
use App\Http\Resources\CrmPipelineStageResource;
use App\Models\CrmDeal;
use App\Services\Crm\DealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmDealController extends Controller
{
    public function __construct(protected DealService $dealService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmDeal::class);

        $deals = $this->dealService->paginate(
            $request->only('pipeline_stage_id', 'assigned_to', 'customer_id', 'lead_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmDealResource::collection($deals));
    }

    public function kanban(): JsonResponse
    {
        $this->authorize('viewAny', CrmDeal::class);

        $columns = $this->dealService->kanban()->map(fn (array $column) => [
            'stage' => new CrmPipelineStageResource($column['stage']),
            'deals' => CrmDealResource::collection($column['deals']),
        ]);

        return $this->success($columns);
    }

    public function store(StoreCrmDealRequest $request): JsonResponse
    {
        $this->authorize('create', CrmDeal::class);

        $deal = $this->dealService->create($request->validated());

        return $this->success(new CrmDealResource($deal), 'Deal created successfully.', 201);
    }

    public function show(CrmDeal $crmDeal): JsonResponse
    {
        $this->authorize('view', $crmDeal);

        return $this->success(new CrmDealResource(
            $crmDeal->load(['lead', 'customer', 'pipelineStage', 'service', 'assignedTo', 'stageHistory'])
        ));
    }

    public function update(UpdateCrmDealRequest $request, CrmDeal $crmDeal): JsonResponse
    {
        $this->authorize('update', $crmDeal);

        $deal = $this->dealService->update($crmDeal, $request->validated());

        return $this->success(new CrmDealResource($deal), 'Deal updated successfully.');
    }

    public function destroy(CrmDeal $crmDeal): JsonResponse
    {
        $this->authorize('delete', $crmDeal);

        $this->dealService->delete($crmDeal);

        return $this->success(null, 'Deal deleted successfully.');
    }

    public function moveStage(MoveCrmDealStageRequest $request, CrmDeal $crmDeal): JsonResponse
    {
        $this->authorize('moveStage', $crmDeal);

        $result = $this->dealService->moveStage(
            $crmDeal,
            $request->validated('pipeline_stage_id'),
            $request->validated('lost_reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new CrmDealResource($result['deal']),
            'message' => 'Deal moved successfully.',
            'errors' => null,
            'meta' => [
                'prompt_customer_service_log' => $result['prompt_customer_service_log'],
            ],
        ]);
    }
}
