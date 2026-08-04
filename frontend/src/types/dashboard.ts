export interface UpcomingPayrollRun {
  id: number
  period_start: string
  period_end: string
  status: string
}

export interface BudgetVsActualLine {
  budget_line_id: number
  account_id: number
  account_code: string
  account_name: string
  budgeted_amount: number
  actual_amount: number
  variance: number
  utilization_percent: number | null
}

export interface BudgetVsActual {
  budget_period_id: number
  name: string
  lines: BudgetVsActualLine[]
}

export interface DashboardSummary {
  branch_id: number | null
  from: string
  to: string
  total_sales: number
  total_purchases: number
  gross_profit_estimate: number
  low_stock_product_count: number
  overdue_invoices_total: number
  overdue_supplier_bills_total: number
  pending_leave_requests_count: number
  upcoming_payroll_run: UpcomingPayrollRun | null
  cash_drawer_variance: number
  budget_vs_actual: BudgetVsActual | null
}
