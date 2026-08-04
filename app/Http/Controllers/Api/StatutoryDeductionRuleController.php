<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatutoryDeductionRule\StoreStatutoryDeductionRuleRequest;
use App\Http\Requests\StatutoryDeductionRule\UpdateStatutoryDeductionRuleRequest;
use App\Http\Resources\StatutoryDeductionRuleResource;
use App\Models\StatutoryDeductionRule;
use App\Services\Hr\StatutoryDeductionRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatutoryDeductionRuleController extends Controller
{
    public function __construct(protected StatutoryDeductionRuleService $statutoryDeductionRuleService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StatutoryDeductionRule::class);

        $rules = $this->statutoryDeductionRuleService->paginate($request->integer('per_page', 15));

        return $this->paginated(StatutoryDeductionRuleResource::collection($rules));
    }

    public function store(StoreStatutoryDeductionRuleRequest $request): JsonResponse
    {
        $this->authorize('create', StatutoryDeductionRule::class);

        $rule = $this->statutoryDeductionRuleService->create($request->validated());

        return $this->success(new StatutoryDeductionRuleResource($rule), 'Statutory deduction rule created successfully.', 201);
    }

    public function show(StatutoryDeductionRule $statutoryDeductionRule): JsonResponse
    {
        $this->authorize('view', $statutoryDeductionRule);

        return $this->success(new StatutoryDeductionRuleResource($statutoryDeductionRule));
    }

    public function update(UpdateStatutoryDeductionRuleRequest $request, StatutoryDeductionRule $statutoryDeductionRule): JsonResponse
    {
        $this->authorize('update', $statutoryDeductionRule);

        $statutoryDeductionRule = $this->statutoryDeductionRuleService->update($statutoryDeductionRule, $request->validated());

        return $this->success(new StatutoryDeductionRuleResource($statutoryDeductionRule), 'Statutory deduction rule updated successfully.');
    }

    public function destroy(StatutoryDeductionRule $statutoryDeductionRule): JsonResponse
    {
        $this->authorize('delete', $statutoryDeductionRule);

        $this->statutoryDeductionRuleService->delete($statutoryDeductionRule);

        return $this->success(null, 'Statutory deduction rule deleted successfully.');
    }
}
