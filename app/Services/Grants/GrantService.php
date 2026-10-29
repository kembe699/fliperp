<?php

namespace App\Services\Grants;

use App\Models\BudgetLine;
use App\Models\Grant;
use App\Models\GrantBudgetLine;
use App\Models\GrantDea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** SOFs, their DEAs and their donor budget lines. */
class GrantService
{
    /** Every table a dimension id can be coded on. */
    protected const CODED_TABLES = ['journal_entry_lines', 'invoice_items', 'purchase_order_items', 'supplier_bills'];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Grant::query()
            ->with('donor')
            ->when($filters['donor_id'] ?? null, fn ($query, $id) => $query->where('donor_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")
            ))
            ->orderBy('code')
            ->paginate($perPage);
    }

    public function create(array $data): Grant
    {
        return Grant::create($data + ['status' => 'active']);
    }

    public function update(Grant $grant, array $data): Grant
    {
        $grant->update($data);

        return $grant->fresh('donor');
    }

    public function delete(Grant $grant): void
    {
        if ($this->isCoded('grant_id', $grant->id) || BudgetLine::where('grant_id', $grant->id)->exists()) {
            throw ValidationException::withMessages([
                'grant' => ["Transactions or budgets are coded to SOF {$grant->code}, so it can only be closed, not deleted."],
            ]);
        }

        DB::transaction(function () use ($grant) {
            $grant->budgetLines()->delete();
            $grant->deas()->delete();
            $grant->delete();
        });
    }

    public function createDea(Grant $grant, array $data): GrantDea
    {
        return $grant->deas()->create($data + ['company_id' => $grant->company_id]);
    }

    public function updateDea(GrantDea $dea, array $data): GrantDea
    {
        $dea->update($data);

        return $dea;
    }

    public function deleteDea(GrantDea $dea): void
    {
        if ($this->isCoded('dea_id', $dea->id) || GrantBudgetLine::where('dea_id', $dea->id)->exists()) {
            throw ValidationException::withMessages([
                'dea' => ["DEA {$dea->code} is used by transactions or budget lines, so it can only be marked inactive."],
            ]);
        }

        $dea->delete();
    }

    /** Sets the budget for an account (and DEA) of the SOF, replacing any existing amount for the same pair. */
    public function saveBudgetLine(Grant $grant, array $data): GrantBudgetLine
    {
        return GrantBudgetLine::updateOrCreate(
            ['grant_id' => $grant->id, 'account_id' => $data['account_id'], 'dea_id' => $data['dea_id'] ?? null],
            ['budgeted_amount' => $data['budgeted_amount']],
        );
    }

    public function deleteBudgetLine(GrantBudgetLine $line): void
    {
        $line->delete();
    }

    public function isCoded(string $column, int $id): bool
    {
        foreach (self::CODED_TABLES as $table) {
            if (DB::table($table)->where($column, $id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
