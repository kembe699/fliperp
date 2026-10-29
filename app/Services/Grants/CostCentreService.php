<?php

namespace App\Services\Grants;

use App\Models\CostCentre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CostCentreService
{
    public function __construct(protected GrantService $grantService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CostCentre::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")
            ))
            ->orderBy('code')
            ->paginate($perPage);
    }

    public function create(array $data): CostCentre
    {
        return CostCentre::create($data);
    }

    public function update(CostCentre $costCentre, array $data): CostCentre
    {
        $costCentre->update($data);

        return $costCentre;
    }

    public function delete(CostCentre $costCentre): void
    {
        if ($this->grantService->isCoded('cost_centre_id', $costCentre->id)) {
            throw ValidationException::withMessages([
                'cost_centre' => ['Transactions are coded to this cost centre, so it can only be marked inactive.'],
            ]);
        }

        $costCentre->delete();
    }
}
