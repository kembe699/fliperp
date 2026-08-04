<?php

namespace App\Services\Logistics;

use App\Models\DeliveryTracking;
use App\Models\Dispatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Dispatch::query()->with('items')->latest('dispatch_date')->paginate($perPage);
    }

    public function create(array $data): Dispatch
    {
        return DB::transaction(function () use ($data) {
            $dispatch = Dispatch::create([
                'branch_id' => $data['branch_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'reference_number' => $data['reference_number'],
                'source_module' => $data['source_module'],
                'source_id' => $data['source_id'] ?? null,
                'dispatched_by' => Auth::id(),
                'dispatch_date' => $data['dispatch_date'],
                'status' => $data['status'] ?? 'pending',
            ]);

            if (! empty($data['items'])) {
                $dispatch->items()->createMany($data['items']);
            }

            return $dispatch->load('items');
        });
    }

    public function update(Dispatch $dispatch, array $data): Dispatch
    {
        return DB::transaction(function () use ($dispatch, $data) {
            $dispatch->update(collect($data)->except('items')->toArray());

            if (array_key_exists('items', $data) && $data['items'] !== null) {
                $dispatch->items()->delete();
                $dispatch->items()->createMany($data['items']);
            }

            return $dispatch->fresh('items');
        });
    }

    public function delete(Dispatch $dispatch): void
    {
        $dispatch->delete();
    }

    public function track(Dispatch $dispatch, array $data): DeliveryTracking
    {
        $tracking = $dispatch->tracking()->create([
            'status' => $data['status'],
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => Auth::id(),
            'recorded_at' => now(),
        ]);

        $dispatch->update(['status' => $this->mapTrackingStatus($data['status'], $dispatch->status)]);

        return $tracking;
    }

    protected function mapTrackingStatus(string $trackingStatus, string $currentStatus): string
    {
        return match (strtolower($trackingStatus)) {
            'delivered' => 'delivered',
            'in_transit', 'in-transit', 'dispatched' => 'in_transit',
            'cancelled' => 'cancelled',
            default => $currentStatus,
        };
    }
}
