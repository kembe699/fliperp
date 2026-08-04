<?php

namespace App\Services\Pos;

use App\Models\Sale;
use App\Models\SalePayment;

class PosReportService
{
    public function dailySummary(string $date, ?int $branchId = null): array
    {
        $baseQuery = Sale::query()->whereDate('sale_date', $date);

        if ($branchId) {
            $baseQuery->where('branch_id', $branchId);
        }

        $completedSaleIds = (clone $baseQuery)->where('status', 'completed')->pluck('id');
        $voidCount = (clone $baseQuery)->where('status', 'voided')->count();

        $totalsByPaymentType = SalePayment::query()
            ->whereIn('sale_payments.sale_id', $completedSaleIds)
            ->join('payment_types', 'payment_types.id', '=', 'sale_payments.payment_type_id')
            ->selectRaw('payment_types.id as payment_type_id, payment_types.name as payment_type_name, SUM(sale_payments.amount) as total')
            ->groupBy('payment_types.id', 'payment_types.name')
            ->get()
            ->map(fn ($row) => [
                'payment_type_id' => $row->payment_type_id,
                'payment_type_name' => $row->payment_type_name,
                'total' => (float) $row->total,
            ]);

        $completedSales = Sale::query()->whereIn('id', $completedSaleIds)->get();

        return [
            'date' => $date,
            'branch_id' => $branchId,
            'total_sales' => round((float) $completedSales->sum('total_amount'), 2),
            'tax_collected' => round((float) $completedSales->sum('tax_amount'), 2),
            'void_count' => $voidCount,
            'totals_by_payment_type' => $totalsByPaymentType->all(),
        ];
    }
}
