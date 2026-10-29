import type { ReportCurrencyInfo } from '@/types/exchangeRate'

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
  grant_id?: number | null
  project_id?: number | null
  cost_centre_id?: number | null
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
  grant_id?: number | null
  grant_code?: string | null
  project_id?: number | null
  project_code?: string | null
  cost_centre_id?: number | null
  cost_centre_code?: string | null
}

export interface BudgetVsActualReport {
  lines: BudgetVsActualLine[]
  totals: { budgeted_amount: number; actual_amount: number; variance: number }
  currency?: ReportCurrencyInfo
}
