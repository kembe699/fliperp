<?php

namespace App\Services\Sales;

use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceListService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PriceList::query()->latest()->paginate($perPage);
    }

    public function create(array $data): PriceList
    {
        return DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                $this->clearExistingDefault();
            }

            return PriceList::create($data);
        });
    }

    public function update(PriceList $priceList, array $data): PriceList
    {
        return DB::transaction(function () use ($priceList, $data) {
            if ($data['is_default'] ?? false) {
                $this->clearExistingDefault($priceList->id);
            }

            $priceList->update($data);

            return $priceList;
        });
    }

    public function delete(PriceList $priceList): void
    {
        if ($priceList->quotations()->exists()) {
            throw ValidationException::withMessages([
                'price_list' => ['Cannot delete a price list already referenced by quotations. Deactivate it instead.'],
            ]);
        }

        $priceList->delete();
    }

    public function addItem(PriceList $priceList, array $data): PriceListItem
    {
        return $priceList->items()->create($data);
    }

    public function updateItem(PriceListItem $item, array $data): PriceListItem
    {
        $item->update($data);

        return $item;
    }

    public function deleteItem(PriceListItem $item): void
    {
        $item->delete();
    }

    protected function clearExistingDefault(?int $exceptId = null): void
    {
        PriceList::query()
            ->where('is_default', true)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }
}
