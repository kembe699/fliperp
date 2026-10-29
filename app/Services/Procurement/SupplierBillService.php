<?php

namespace App\Services\Procurement;

use App\Models\SupplierBill;
use App\Services\Finance\ExchangeRateService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SupplierBillService
{
    public function __construct(protected ExchangeRateService $exchangeRateService) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return SupplierBill::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('bill_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('bill_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner
                    ->where('reference_number', 'ilike', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'ilike', "%{$search}%"))
            ))
            ->latest('bill_date')
            ->paginate($perPage);
    }

    public function create(array $data): SupplierBill
    {
        $subtotal = (float) $data['subtotal'];
        $taxAmount = (float) ($data['tax_amount'] ?? 0);
        [$currency, $rate] = $this->exchangeRateService->resolve(
            Auth::user()->company_id, $data['currency_code'] ?? null, $data['bill_date'], $data['exchange_rate'] ?? null,
        );

        $bill = SupplierBill::create([
            'supplier_id' => $data['supplier_id'],
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'reference_number' => $data['reference_number'],
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
            'status' => 'unpaid',
            'currency_code' => $currency,
            'exchange_rate' => $rate,
        ]);

        return $this->refreshStatus($bill);
    }

    public function update(SupplierBill $bill, array $data): SupplierBill
    {
        if ((float) $bill->amount_paid > 0) {
            throw ValidationException::withMessages([
                'bill' => ['Cannot edit a bill that already has payments applied.'],
            ]);
        }

        $subtotal = array_key_exists('subtotal', $data) ? (float) $data['subtotal'] : (float) $bill->subtotal;
        $taxAmount = array_key_exists('tax_amount', $data) ? (float) $data['tax_amount'] : (float) $bill->tax_amount;

        $payload = collect($data)->except(['subtotal', 'tax_amount', 'currency_code', 'exchange_rate'])->toArray();

        if (array_key_exists('currency_code', $data) || array_key_exists('exchange_rate', $data) || isset($data['bill_date'])) {
            $keepsManualRate = ! array_key_exists('currency_code', $data) && ! isset($data['bill_date']);
            [$payload['currency_code'], $payload['exchange_rate']] = $this->exchangeRateService->resolve(
                $bill->company_id,
                $data['currency_code'] ?? $bill->currency_code,
                $data['bill_date'] ?? $bill->bill_date->toDateString(),
                $data['exchange_rate'] ?? ($keepsManualRate ? $bill->exchange_rate : null),
            );
        }
        $payload['subtotal'] = $subtotal;
        $payload['tax_amount'] = $taxAmount;
        $payload['total_amount'] = round($subtotal + $taxAmount, 2);

        $bill->update($payload);

        return $this->refreshStatus($bill);
    }

    public function delete(SupplierBill $bill): void
    {
        if ((float) $bill->amount_paid > 0 || $bill->journal_entry_id) {
            throw ValidationException::withMessages([
                'bill' => ['Cannot delete a bill that has payments or a posted journal entry.'],
            ]);
        }

        $bill->delete();
    }

    public function refreshStatus(SupplierBill $bill): SupplierBill
    {
        $status = $this->determineStatus($bill);

        if ($status !== $bill->status) {
            $bill->update(['status' => $status]);
        }

        return $bill;
    }

    protected function determineStatus(SupplierBill $bill): string
    {
        $totalAmount = (float) $bill->total_amount;
        $amountPaid = (float) $bill->amount_paid;

        if ($totalAmount > 0 && $amountPaid >= $totalAmount) {
            return 'paid';
        }

        if (Carbon::parse($bill->due_date)->isBefore(Carbon::today())) {
            return 'overdue';
        }

        return $amountPaid > 0 ? 'partially_paid' : 'unpaid';
    }
}
