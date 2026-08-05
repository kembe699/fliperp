<?php

namespace App\Services\Pos;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * $additionalScope lets callers layer on extra query constraints (e.g.
     * the CRM staff-visibility restriction) without duplicating these
     * filters — it's applied last, after the standard filters below.
     */
    public function paginate(array $filters = [], int $perPage = 15, ?\Closure $additionalScope = null): LengthAwarePaginator
    {
        return Customer::query()
            ->when($filters['customer_type'] ?? null, fn (Builder $query, $type) => $query->where('customer_type', $type))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
            ->when(($filters['is_active'] ?? null) !== null, fn (Builder $query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
            ))
            ->when($additionalScope, fn (Builder $query) => $additionalScope($query))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Customer
    {
        // Fresh reload so DB-applied defaults (e.g. is_active) are reflected
        // in the response instead of showing null on the in-memory instance.
        return Customer::create($data)->fresh();
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        if ($customer->sales()->exists()) {
            throw ValidationException::withMessages([
                'customer' => ['Cannot delete a customer with sales history. Deactivate it instead.'],
            ]);
        }

        $customer->delete();
    }
}
