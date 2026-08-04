export type BudgetPeriodStatus = 'draft' | 'active' | 'closed'

export interface BudgetPeriod {
  id: number
  company_id: number
  name: string
  start_date: string
  end_date: string
  status: BudgetPeriodStatus
}

export interface BudgetLine {
  id: number
  budget_period_id: number
  branch_id: number | null
  department_id: number | null
  account_id: number
  budgeted_amount: number
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
