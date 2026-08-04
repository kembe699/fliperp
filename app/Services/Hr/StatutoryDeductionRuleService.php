<?php

namespace App\Services\Hr;

use App\Models\StatutoryDeductionRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StatutoryDeductionRuleService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return StatutoryDeductionRule::query()->latest()->paginate($perPage);
    }

    public function create(array $data): StatutoryDeductionRule
    {
        return StatutoryDeductionRule::create($data);
    }

    public function update(StatutoryDeductionRule $rule, array $data): StatutoryDeductionRule
    {
        $rule->update($data);

        return $rule;
    }

    public function delete(StatutoryDeductionRule $rule): void
    {
        $rule->delete();
    }
}
