<?php

namespace App\Services\Finance;

use App\Models\PaymentApprovalRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settings › Payment Approvals: amount bands (in the base currency) and the
 * ordered approvers for each. Active bands may not overlap, so every amount
 * has at most one rule; an amount with no rule cannot be submitted.
 */
class PaymentApprovalRuleService
{
    /** Platform-level and portal roles are never offered as approvers. */
    public const EXCLUDED_ROLES = ['super_admin', 'platform_admin', 'employee'];

    public function all(): Collection
    {
        return PaymentApprovalRule::query()->with('steps.role', 'steps.user')->orderBy('min_amount')->get();
    }

    /**
     * @param  array{name:string, min_amount:float|int, max_amount?:float|int|null, is_active?:bool, steps:array<int, array{label:string, role_id?:int|null, user_id?:int|null}>}  $data
     */
    public function create(array $data, User $user): PaymentApprovalRule
    {
        $this->validateRule($data, $user->company_id);

        return DB::transaction(function () use ($data, $user) {
            $rule = PaymentApprovalRule::create([
                'company_id' => $user->company_id,
                'name' => $data['name'],
                'min_amount' => $data['min_amount'],
                'max_amount' => $data['max_amount'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncSteps($rule, $data['steps']);

            return $rule->load('steps.role', 'steps.user');
        });
    }

    public function update(PaymentApprovalRule $rule, array $data, User $user): PaymentApprovalRule
    {
        $fields = array_intersect_key($data, array_flip(['name', 'min_amount', 'max_amount', 'is_active']));
        $steps = $data['steps'] ?? null;

        // Validate the rule as it will be after the change.
        $this->validateRule(array_merge(
            $rule->only(['name', 'min_amount', 'max_amount', 'is_active']),
            $fields,
            ['steps' => $steps ?? $rule->steps->map->only(['label', 'role_id', 'user_id'])->all()],
        ), $user->company_id, $rule->id);

        return DB::transaction(function () use ($rule, $fields, $steps) {
            $rule->update($fields);
            if ($steps !== null) {
                $this->syncSteps($rule, $steps);
            }

            return $rule->fresh(['steps.role', 'steps.user']);
        });
    }

    /** Requests already submitted keep their copied steps, so deleting a rule never strands them. */
    public function delete(PaymentApprovalRule $rule): void
    {
        $rule->delete();
    }

    /** Roles a step can name: the system roles plus this company's own, minus platform/portal roles. */
    public function assignableRoles(int $companyId): Collection
    {
        return Role::query()
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->whereNotIn('name', self::EXCLUDED_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);
    }

    protected function syncSteps(PaymentApprovalRule $rule, array $steps): void
    {
        $rule->steps()->delete();
        foreach (array_values($steps) as $i => $step) {
            $rule->steps()->create([
                'sequence' => $i + 1,
                'label' => $step['label'],
                'role_id' => $step['role_id'] ?? null,
                'user_id' => $step['user_id'] ?? null,
            ]);
        }
    }

    protected function validateRule(array $data, int $companyId, ?int $ignoreId = null): void
    {
        $errors = [];
        $min = (float) $data['min_amount'];
        $max = isset($data['max_amount']) && $data['max_amount'] !== '' ? (float) $data['max_amount'] : null;

        if ($min < 0) {
            $errors['min_amount'][] = 'The lower limit cannot be negative.';
        }
        if ($max !== null && $max <= $min) {
            $errors['max_amount'][] = 'The upper limit must be more than the lower limit.';
        }

        if (($data['is_active'] ?? true) && empty($errors)) {
            $overlap = PaymentApprovalRule::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->get()
                ->first(fn (PaymentApprovalRule $other) => $min < ($other->max_amount === null ? INF : (float) $other->max_amount)
                    && (float) $other->min_amount < ($max ?? INF));
            if ($overlap) {
                $errors['min_amount'][] = "This amount range overlaps the rule \"{$overlap->name}\". Each amount may fall under one active rule only.";
            }
        }

        $steps = $data['steps'] ?? [];
        if (count($steps) === 0) {
            $errors['steps'][] = 'Add at least one approver.';
        }
        $roleIds = $this->assignableRoles($companyId)->pluck('id')->all();
        foreach (array_values($steps) as $i => $step) {
            $hasRole = ! empty($step['role_id']);
            $hasUser = ! empty($step['user_id']);
            if ($hasRole === $hasUser) {
                $errors["steps.{$i}.role_id"][] = 'Choose either a role or a named user for each approver.';
            } elseif ($hasRole && ! in_array((int) $step['role_id'], $roleIds, true)) {
                $errors["steps.{$i}.role_id"][] = 'The selected role does not exist.';
            } elseif ($hasUser && ! User::query()->where('company_id', $companyId)->where('is_active', true)->whereKey($step['user_id'])->exists()) {
                $errors["steps.{$i}.user_id"][] = 'The selected user does not exist or is not active.';
            }
            if (blank($step['label'] ?? null)) {
                $errors["steps.{$i}.label"][] = 'Give each approver a label, e.g. "Program Officer".';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
