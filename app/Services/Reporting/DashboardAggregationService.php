<?php

namespace App\Services\Reporting;

use App\Models\BudgetPeriod;
use App\Models\CashDrawerSession;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SupplierBill;
use App\Services\Budgeting\BudgetVsActualService;

/**
 * Shape of summary():
 * [
 *   'branch_id' => ?int, 'from' => 'Y-m-d', 'to' => 'Y-m-d',
 *   'total_sales' => float, 'total_purchases' => float, 'gross_profit_estimate' => float,
 *   'low_stock_product_count' => int,
 *   'overdue_invoices_total' => float, 'overdue_supplier_bills_total' => float,
 *   'pending_leave_requests_count' => int,
 *   'upcoming_payroll_run' => ?['id' => int, 'period_start' => string, 'period_end' => string, 'status' => string],
 *   'cash_drawer_variance' => float,
 *   'budget_vs_actual' => ?['budget_period_id' => int, 'name' => string, 'lines' => array],
 * ]
 */
class DashboardAggregationService
{
    public const REVENUE_ACCOUNT_CODE = '4000';

    public const COGS_ACCOUNT_CODE = '5300';

    public function __construct(protected BudgetVsActualService $budgetVsActualService) {}

    public function summary(int $companyId, ?int $branchId, string $from, string $to): array
    {
        return [
            'branch_id' => $branchId,
            'from' => $from,
            'to' => $to,
            'total_sales' => $this->totalSales($companyId, $branchId, $from, $to),
            'total_purchases' => $this->totalPurchases($companyId, $from, $to),
            'gross_profit_estimate' => $this->grossProfitEstimate($companyId, $branchId, $from, $to),
            'low_stock_product_count' => $this->lowStockProductCount($companyId),
            'overdue_invoices_total' => $this->overdueInvoicesTotal($companyId, $branchId),
            'overdue_supplier_bills_total' => $this->overdueSupplierBillsTotal($companyId),
            'pending_leave_requests_count' => $this->pendingLeaveRequestsCount($companyId, $branchId),
            'upcoming_payroll_run' => $this->upcomingPayrollRun($companyId, $branchId),
            'cash_drawer_variance' => $this->cashDrawerVariance($companyId, $branchId, $from, $to),
            'budget_vs_actual' => $this->budgetVsActual($companyId),
        ];
    }

    protected function totalSales(int $companyId, ?int $branchId, string $from, string $to): float
    {
        return round((float) Sale::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('total_amount'), 2);
    }

    protected function totalPurchases(int $companyId, string $from, string $to): float
    {
        return round((float) SupplierBill::query()
            ->where('company_id', $companyId)
            ->whereBetween('bill_date', [$from, $to])
            ->sum('total_amount'), 2);
    }

    protected function grossProfitEstimate(int $companyId, ?int $branchId, string $from, string $to): float
    {
        $revenueAccount = ChartOfAccount::query()->where('company_id', $companyId)->where('code', self::REVENUE_ACCOUNT_CODE)->first();
        $cogsAccount = ChartOfAccount::query()->where('company_id', $companyId)->where('code', self::COGS_ACCOUNT_CODE)->first();

        $baseQuery = fn (int $accountId) => JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $accountId)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->when($branchId, fn ($query) => $query->where('journal_entries.branch_id', $branchId));

        $revenue = $revenueAccount ? (float) $baseQuery($revenueAccount->id)->sum('journal_entry_lines.credit') - (float) $baseQuery($revenueAccount->id)->sum('journal_entry_lines.debit') : 0.0;
        $cogs = $cogsAccount ? (float) $baseQuery($cogsAccount->id)->sum('journal_entry_lines.debit') - (float) $baseQuery($cogsAccount->id)->sum('journal_entry_lines.credit') : 0.0;

        return round($revenue - $cogs, 2);
    }

    protected function lowStockProductCount(int $companyId): int
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('track_inventory', true)
            ->whereRaw('(select coalesce(sum(stock_levels.quantity_on_hand), 0) from stock_levels where stock_levels.product_id = products.id) <= products.reorder_level')
            ->count();
    }

    protected function overdueInvoicesTotal(int $companyId, ?int $branchId): float
    {
        return round((float) Invoice::query()
            ->where('company_id', $companyId)
            ->overdue()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->sum(fn (Invoice $invoice) => (float) $invoice->total_amount - (float) $invoice->amount_paid), 2);
    }

    protected function overdueSupplierBillsTotal(int $companyId): float
    {
        return round((float) SupplierBill::query()
            ->where('company_id', $companyId)
            ->overdue()
            ->get()
            ->sum(fn (SupplierBill $bill) => (float) $bill->total_amount - (float) $bill->amount_paid), 2);
    }

    protected function pendingLeaveRequestsCount(int $companyId, ?int $branchId): int
    {
        return LeaveRequest::query()
            ->where('status', 'pending')
            ->whereHas('employee', function ($query) use ($companyId, $branchId) {
                $query->where('company_id', $companyId);

                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->count();
    }

    protected function upcomingPayrollRun(int $companyId, ?int $branchId): ?array
    {
        $payrollRun = PayrollRun::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('period_start')
            ->first();

        if (! $payrollRun) {
            return null;
        }

        return [
            'id' => $payrollRun->id,
            'period_start' => $payrollRun->period_start->toDateString(),
            'period_end' => $payrollRun->period_end->toDateString(),
            'status' => $payrollRun->status,
        ];
    }

    protected function cashDrawerVariance(int $companyId, ?int $branchId, string $from, string $to): float
    {
        return round((float) CashDrawerSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->whereDate('closed_at', '>=', $from)
            ->whereDate('closed_at', '<=', $to)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('variance'), 2);
    }

    protected function budgetVsActual(int $companyId): ?array
    {
        $budgetPeriod = BudgetPeriod::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->first();

        if (! $budgetPeriod) {
            return null;
        }

        return [
            'budget_period_id' => $budgetPeriod->id,
            'name' => $budgetPeriod->name,
            'lines' => $this->budgetVsActualService->compare($budgetPeriod),
        ];
    }
}
