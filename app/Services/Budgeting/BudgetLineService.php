<?php

namespace App\Services\Budgeting;

use App\Models\BudgetLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BudgetLineService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return BudgetLine::query()
            ->whereHas('budgetPeriod')
            ->when($filters['budget_period_id'] ?? null, fn ($query, $id) => $query->where('budget_period_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): BudgetLine
    {
        return BudgetLine::create($data);
    }

    public function update(BudgetLine $budgetLine, array $data): BudgetLine
    {
        $budgetLine->update($data);

        return $budgetLine;
    }

    public function delete(BudgetLine $budgetLine): void
    {
        $budgetLine->delete();
    }
}
