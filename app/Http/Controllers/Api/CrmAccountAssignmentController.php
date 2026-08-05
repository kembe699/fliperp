<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmAccountAssignment\StoreCrmAccountAssignmentRequest;
use App\Http\Requests\CrmAccountAssignment\UpdateCrmAccountAssignmentRequest;
use App\Http\Resources\CrmAccountAssignmentResource;
use App\Models\CrmAccountAssignment;
use App\Services\Crm\AccountAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmAccountAssignmentController extends Controller
{
    public function __construct(protected AccountAssignmentService $accountAssignmentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmAccountAssignment::class);

        $assignments = $this->accountAssignmentService->paginate(
            $request->only('customer_id', 'user_id', 'active'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmAccountAssignmentResource::collection($assignments));
    }

    public function current(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmAccountAssignment::class);

        $assignments = $this->accountAssignmentService->currentForCustomer($request->integer('customer_id'));

        return $this->success(CrmAccountAssignmentResource::collection($assignments));
    }

    public function store(StoreCrmAccountAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', CrmAccountAssignment::class);

        $assignment = $this->accountAssignmentService->assign($request->validated());

        return $this->success(new CrmAccountAssignmentResource($assignment), 'Staff member assigned successfully.', 201);
    }

    public function show(CrmAccountAssignment $crmAccountAssignment): JsonResponse
    {
        $this->authorize('view', $crmAccountAssignment);

        return $this->success(new CrmAccountAssignmentResource($crmAccountAssignment->load(['customer', 'user'])));
    }

    public function update(UpdateCrmAccountAssignmentRequest $request, CrmAccountAssignment $crmAccountAssignment): JsonResponse
    {
        $this->authorize('update', $crmAccountAssignment);

        $assignment = $this->accountAssignmentService->update($crmAccountAssignment, $request->validated());

        return $this->success(new CrmAccountAssignmentResource($assignment), 'Assignment updated successfully.');
    }

    public function destroy(CrmAccountAssignment $crmAccountAssignment): JsonResponse
    {
        $this->authorize('delete', $crmAccountAssignment);

        $this->accountAssignmentService->delete($crmAccountAssignment);

        return $this->success(null, 'Assignment deleted successfully.');
    }

    public function unassign(CrmAccountAssignment $crmAccountAssignment): JsonResponse
    {
        $this->authorize('unassign', $crmAccountAssignment);

        $assignment = $this->accountAssignmentService->unassign($crmAccountAssignment);

        return $this->success(new CrmAccountAssignmentResource($assignment), 'Staff member unassigned successfully.');
    }
}
