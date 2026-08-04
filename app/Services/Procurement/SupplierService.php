<?php

namespace App\Services\Procurement;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Supplier::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier;
    }

    public function delete(Supplier $supplier): void
    {
        $hasDownstreamRecords = $supplier->purchaseOrders()->exists()
            || $supplier->goodsReceivedNotes()->exists()
            || $supplier->bills()->exists()
            || $supplier->payments()->exists();

        if ($hasDownstreamRecords) {
            throw ValidationException::withMessages([
                'supplier' => ['Cannot delete a supplier with purchase orders, GRNs, bills or payments. Deactivate it instead.'],
            ]);
        }

        $supplier->delete();
    }

    public function statement(Supplier $supplier): array
    {
        $bills = $supplier->bills()->get()->map(fn ($bill) => [
            'type' => 'bill',
            'date' => $bill->bill_date->toDateString(),
            'reference_number' => $bill->reference_number,
            'debit' => (float) $bill->total_amount,
            'credit' => 0.0,
        ]);

        $payments = $supplier->payments()->get()->map(fn ($payment) => [
            'type' => 'payment',
            'date' => $payment->payment_date->toDateString(),
            'reference_number' => $payment->reference_number,
            'debit' => 0.0,
            'credit' => (float) $payment->amount,
        ]);

        $runningBalance = 0.0;

        $transactions = $bills->concat($payments)
            ->sortBy('date')
            ->values()
            ->map(function (array $transaction) use (&$runningBalance) {
                $runningBalance += $transaction['debit'] - $transaction['credit'];
                $transaction['running_balance'] = round($runningBalance, 2);

                return $transaction;
            });

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'closing_balance' => round($runningBalance, 2),
            'transactions' => $transactions->all(),
        ];
    }
}
