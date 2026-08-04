<?php

namespace App\Services\Budgeting;

use App\Models\BudgetPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BudgetPeriodService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return BudgetPeriod::query()->latest('start_date')->paginate($perPage);
    }

    public function create(array $data): BudgetPeriod
    {
        return BudgetPeriod::create($data);
    }

    public function update(BudgetPeriod $budgetPeriod, array $data): BudgetPeriod
    {
        $budgetPeriod->update($data);

        return $budgetPeriod;
    }

    public function delete(BudgetPeriod $budgetPeriod): void
    {
        $budgetPeriod->delete();
    }
}
