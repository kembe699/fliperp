<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\CrmLead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmLead::query()
            ->with(['assignedTo', 'branch'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): CrmLead
    {
        return CrmLead::create($data + ['status' => 'open']);
    }

    public function update(CrmLead $lead, array $data): CrmLead
    {
        if ($lead->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only open leads can be edited.'],
            ]);
        }

        $lead->update($data);

        return $lead;
    }

    public function delete(CrmLead $lead): void
    {
        $lead->delete();
    }

    public function convert(CrmLead $lead, array $data = []): CrmLead
    {
        if ($lead->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only open leads can be converted.'],
            ]);
        }

        $phone = $data['phone'] ?? $lead->phone;

        if (! $phone) {
            throw ValidationException::withMessages([
                'phone' => ['This lead has no phone number on file — supply one to convert it to a customer.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $data, $phone) {
            $customer = Customer::create([
                'company_id' => $lead->company_id,
                'branch_id' => $data['branch_id'] ?? $lead->branch_id,
                'name' => $lead->company_name ?: $lead->name,
                'email' => $lead->email,
                'phone' => $phone,
                'customer_type' => $data['customer_type'] ?? 'regular',
                'credit_limit' => $data['credit_limit'] ?? 0,
                'is_active' => true,
            ]);

            $lead->update([
                'status' => 'converted',
                'converted_customer_id' => $customer->id,
            ]);

            $lead->deals()
                ->whereNull('closed_at')
                ->update(['customer_id' => $customer->id]);

            return $lead->fresh(['convertedCustomer', 'deals']);
        });
    }
}
