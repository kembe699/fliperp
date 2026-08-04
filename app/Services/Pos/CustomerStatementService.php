<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalePayment;

class CustomerStatementService
{
    public function statement(Customer $customer): array
    {
        $sales = $customer->sales()->whereIn('status', ['completed', 'refunded'])->get()->map(fn ($sale) => [
            'type' => 'sale',
            'date' => $sale->sale_date->toDateString(),
            'reference_number' => $sale->reference_number,
            'debit' => (float) $sale->total_amount,
            'credit' => 0.0,
        ]);

        $salePayments = SalePayment::query()
            ->whereIn('sale_id', $customer->sales()->pluck('id'))
            ->get()
            ->map(fn ($payment) => [
                'type' => 'sale_payment',
                'date' => $payment->paid_at->toDateString(),
                'reference_number' => $payment->reference_number,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);

        $invoices = $customer->invoices()->whereNotIn('status', ['draft', 'cancelled'])->get()->map(fn ($invoice) => [
            'type' => 'invoice',
            'date' => $invoice->invoice_date->toDateString(),
            'reference_number' => $invoice->reference_number,
            'debit' => (float) $invoice->total_amount,
            'credit' => 0.0,
        ]);

        $customerPayments = CustomerPayment::query()
            ->whereIn('invoice_id', $customer->invoices()->pluck('id'))
            ->get()
            ->map(fn ($payment) => [
                'type' => 'invoice_payment',
                'date' => $payment->payment_date->toDateString(),
                'reference_number' => $payment->reference_number,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);

        $runningBalance = 0.0;

        $transactions = $sales->concat($salePayments)->concat($invoices)->concat($customerPayments)
            ->sortBy('date')
            ->values()
            ->map(function (array $transaction) use (&$runningBalance) {
                $runningBalance += $transaction['debit'] - $transaction['credit'];
                $transaction['running_balance'] = round($runningBalance, 2);

                return $transaction;
            });

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'closing_balance' => round($runningBalance, 2),
            'transactions' => $transactions->all(),
        ];
    }
}
