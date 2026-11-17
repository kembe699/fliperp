<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentApprovalRuleResource;
use App\Models\PaymentApprovalRule;
use App\Models\User;
use App\Services\Finance\PaymentApprovalRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings › Payment Approvals: amount bands and their approvers. */
class PaymentApprovalRuleController extends Controller
{
    public function __construct(protected PaymentApprovalRuleService $rules) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentApprovalRule::class);

        return $this->success([
            'rules' => PaymentApprovalRuleResource::collection($this->rules->all()),
            // What a step can name: roles (system + this company's) and this company's active users.
            'roles' => $this->rules->assignableRoles($request->user()->company_id)->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'users' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage', PaymentApprovalRule::class);

        return $this->success(new PaymentApprovalRuleResource($this->rules->create($this->validated($request, true), $request->user())), 'Approval rule saved.', 201);
    }

    public function update(Request $request, PaymentApprovalRule $paymentApprovalRule): JsonResponse
    {
        $this->authorize('manage', $paymentApprovalRule);

        return $this->success(new PaymentApprovalRuleResource($this->rules->update($paymentApprovalRule, $this->validated($request, false), $request->user())), 'Approval rule updated.');
    }

    public function destroy(PaymentApprovalRule $paymentApprovalRule): JsonResponse
    {
        $this->authorize('manage', $paymentApprovalRule);
        $this->rules->delete($paymentApprovalRule);

        return $this->success(null, 'Approval rule deleted.');
    }

    protected function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$req, 'string', 'max:255'],
            'min_amount' => [$req, 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => [$req, 'array', 'min:1', 'max:10'],
            'steps.*.label' => ['required', 'string', 'max:100'],
            'steps.*.role_id' => ['nullable', 'integer'],
            'steps.*.user_id' => ['nullable', 'integer'],
        ]);
    }
}
