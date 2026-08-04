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
        // Postgres inserts only RETURNING the id, so the DB-level column
        // default (status) never makes it back onto the in-memory model
        // unless set explicitly here — without this the create response
        // shows status: null even though the row is actually 'draft'.
        return BudgetPeriod::create(['status' => 'draft', ...$data]);
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
