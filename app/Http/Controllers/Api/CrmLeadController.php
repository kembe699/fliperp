<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmLead\ConvertCrmLeadRequest;
use App\Http\Requests\CrmLead\StoreCrmLeadRequest;
use App\Http\Requests\CrmLead\UpdateCrmLeadRequest;
use App\Http\Resources\CrmLeadResource;
use App\Models\CrmLead;
use App\Services\Crm\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmLeadController extends Controller
{
    public function __construct(protected LeadService $leadService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmLead::class);

        $leads = $this->leadService->paginate(
            $request->only('status', 'source', 'assigned_to', 'branch_id', 'search'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmLeadResource::collection($leads));
    }

    public function store(StoreCrmLeadRequest $request): JsonResponse
    {
        $this->authorize('create', CrmLead::class);

        $lead = $this->leadService->create($request->validated());

        return $this->success(new CrmLeadResource($lead), 'Lead created successfully.', 201);
    }

    public function show(CrmLead $crmLead): JsonResponse
    {
        $this->authorize('view', $crmLead);

        return $this->success(new CrmLeadResource($crmLead->load(['assignedTo', 'branch'])));
    }

    public function update(UpdateCrmLeadRequest $request, CrmLead $crmLead): JsonResponse
    {
        $this->authorize('update', $crmLead);

        $lead = $this->leadService->update($crmLead, $request->validated());

        return $this->success(new CrmLeadResource($lead), 'Lead updated successfully.');
    }

    public function destroy(CrmLead $crmLead): JsonResponse
    {
        $this->authorize('delete', $crmLead);

        $this->leadService->delete($crmLead);

        return $this->success(null, 'Lead deleted successfully.');
    }

    public function convert(ConvertCrmLeadRequest $request, CrmLead $crmLead): JsonResponse
    {
        $this->authorize('convert', $crmLead);

        $lead = $this->leadService->convert($crmLead, $request->validated());

        return $this->success(new CrmLeadResource($lead), 'Lead converted to customer successfully.');
    }
}
