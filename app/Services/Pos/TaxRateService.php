<?php

namespace App\Services\Pos;

use App\Models\TaxRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxRateService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return TaxRate::query()->latest()->paginate($perPage);
    }

    public function create(array $data): TaxRate
    {
        return TaxRate::create($data);
    }

    public function update(TaxRate $taxRate, array $data): TaxRate
    {
        $taxRate->update($data);

        return $taxRate;
    }

    public function delete(TaxRate $taxRate): void
    {
        $taxRate->delete();
    }
}
