<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetMaintenanceLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AssetService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Asset::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Asset
    {
        $data['current_value'] = $data['current_value'] ?? $data['purchase_cost'];

        return Asset::create($data);
    }

    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $asset;
    }

    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    public function assign(Asset $asset, int $employeeId): Asset
    {
        if ($asset->status === 'disposed') {
            throw ValidationException::withMessages([
                'status' => ['A disposed asset cannot be assigned.'],
            ]);
        }

        $asset->update([
            'assigned_to_employee_id' => $employeeId,
            'status' => 'in_use',
        ]);

        return $asset;
    }

    public function dispose(Asset $asset): Asset
    {
        $asset->update([
            'status' => 'disposed',
            'assigned_to_employee_id' => null,
        ]);

        return $asset;
    }

    public function addMaintenanceLog(Asset $asset, array $data): AssetMaintenanceLog
    {
        return $asset->maintenanceLogs()->create($data);
    }
}
