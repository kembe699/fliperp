<?php

namespace App\Services\Logistics;

use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VehicleService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Vehicle::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Vehicle
    {
        return Vehicle::create($data);
    }

    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update($data);

        return $vehicle;
    }

    public function delete(Vehicle $vehicle): void
    {
        $vehicle->delete();
    }
}
