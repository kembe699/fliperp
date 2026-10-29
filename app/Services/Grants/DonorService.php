<?php

namespace App\Services\Grants;

use App\Models\Donor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class DonorService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Donor::query()
            ->withCount('grants')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): Donor
    {
        return Donor::create($data);
    }

    public function update(Donor $donor, array $data): Donor
    {
        $donor->update($data);

        return $donor;
    }

    public function delete(Donor $donor): void
    {
        if ($donor->grants()->exists()) {
            throw ValidationException::withMessages([
                'donor' => ['This donor has SOFs; delete or reassign them first, or mark the donor inactive.'],
            ]);
        }

        $donor->delete();
    }
}
