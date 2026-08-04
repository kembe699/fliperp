<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChartOfAccountService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ChartOfAccount::query()->latest()->paginate($perPage);
    }

    public function create(array $data): ChartOfAccount
    {
        return ChartOfAccount::create($data);
    }

    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        $account->update($data);

        return $account;
    }

    public function delete(ChartOfAccount $account): void
    {
        $account->delete();
    }
}
