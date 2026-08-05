<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\CrmCustomerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerServiceRecordService
{
    public function paginate(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return CrmCustomerService::query()
            ->with(['service', 'deal'])
            ->where('customer_id', $customerId)
            ->latest('start_date')
            ->paginate($perPage);
    }

    public function create(array $data): CrmCustomerService
    {
        $data['status'] = $data['status'] ?? 'active';

        return CrmCustomerService::create($data)->load(['service', 'deal']);
    }

    public function update(CrmCustomerService $customerService, array $data): CrmCustomerService
    {
        $customerService->update($data);

        return $customerService;
    }

    public function delete(CrmCustomerService $customerService): void
    {
        $customerService->delete();
    }

    public function statement(Customer $customer): array
    {
        $runningTotal = 0.0;

        $entries = CrmCustomerService::query()
            ->with('service')
            ->where('customer_id', $customer->id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(function (CrmCustomerService $entry) use (&$runningTotal) {
                $runningTotal += (float) $entry->price_charged;

                return [
                    'id' => $entry->id,
                    'service_id' => $entry->crm_service_id,
                    'service_name' => $entry->service?->name,
                    'deal_id' => $entry->deal_id,
                    'price_charged' => (float) $entry->price_charged,
                    'start_date' => $entry->start_date->toDateString(),
                    'end_date' => $entry->end_date?->toDateString(),
                    'status' => $entry->status,
                    'running_total' => round($runningTotal, 2),
                ];
            });

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'total_spent' => round($runningTotal, 2),
            'entries' => $entries->all(),
        ];
    }
}
