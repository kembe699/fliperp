import type { BudgetVsActualLine } from '@/types/budgeting'

export type MeProjectStatus = 'planned' | 'ongoing' | 'completed'

export interface MeProject {
  id: number
  company_id: number
  code?: string | null
  name: string
  description: string | null
  start_date: string
  end_date: string | null
  status: MeProjectStatus
  budget_period_id: number | null
}

export interface MeIndicator {
  id: number
  me_project_id: number
  name: string
  unit_of_measure: string | null
  target_value: number
  baseline_value: number
}

export type MeActivityStatus = 'not_started' | 'in_progress' | 'completed' | 'delayed'

export interface MeActivity {
  id: number
  me_project_id: number
  name: string
  start_date: string
  end_date: string | null
  responsible_employee_id: number | null
  status: MeActivityStatus
}

export interface MeResult {
  id: number
  me_indicator_id: number
  reporting_period: string
  actual_value: number
  notes: string | null
  recorded_by: number | null
  recorded_at: string
}

export interface MeIndicatorProgress {
  indicator_id: number
  name: string
  unit_of_measure: string | null
  baseline_value: number
  target_value: number
  actual_value: number
  progress_percent: number | null
  last_reporting_period: string | null
}

export interface MeProjectDashboard {
  project_id: number
  project_name: string
  status: MeProjectStatus
  indicators: MeIndicatorProgress[]
  activities_summary: {
    total: number
    completed: number
    completion_percent: number | null
  }
  /** Budget vs actual (posted ledger activity) from the project's budget period; null if none is linked. */
  finance: {
    budget_period_id: number
    budget_period_name: string
    start_date: string | null
    end_date: string | null
    budgeted_total: number
    actual_total: number
    variance: number
    utilization_percent: number | null
    lines: BudgetVsActualLine[]
  } | null
}
