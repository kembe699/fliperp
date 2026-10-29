import type { ReportCurrencyInfo } from '@/types/exchangeRate'

/** The coding block on a line (Save the Children style): SOF, DEA, project and cost centre, each optional. */
export interface Coding {
  grant_id: number | null
  dea_id: number | null
  project_id: number | null
  cost_centre_id: number | null
}

/** What API resources return for a coded line: the ids plus the codes people read. */
export interface CodingLabels extends Coding {
  grant_code?: string | null
  dea_code?: string | null
  project_code?: string | null
  cost_centre_code?: string | null
}

export const EMPTY_CODING: Coding = { grant_id: null, dea_id: null, project_id: null, cost_centre_id: null }

export interface Donor {
  id: number
  code: string | null
  name: string
  contact_email: string | null
  notes: string | null
  is_active: boolean
  grants_count?: number
}

export type GrantStatus = 'active' | 'closed'

export interface GrantDea {
  id: number
  grant_id: number
  code: string
  name: string
  is_active: boolean
}

export interface GrantBudgetLine {
  id: number
  grant_id: number
  account_id: number
  account_code?: string
  account_name?: string
  dea_id: number | null
  dea_code?: string | null
  budgeted_amount: number
}

export interface Grant {
  id: number
  donor_id: number
  donor_name?: string | null
  code: string
  name: string
  currency_code: string
  start_date: string
  end_date: string
  budget_amount: number
  status: GrantStatus
  notes: string | null
  deas?: GrantDea[]
  budget_lines?: GrantBudgetLine[]
}

export interface GrantSummary {
  grant_id: number
  actual_sof: number
  actual_base: number
  remaining_award_sof: number
  burn_percent: number | null
}

export interface CostCentre {
  id: number
  code: string
  name: string
  is_active: boolean
}

export interface DimensionOptions {
  grants: {
    id: number
    code: string
    name: string
    donor_name: string | null
    currency_code: string
    start_date: string
    end_date: string
    status: GrantStatus
    deas: { id: number; code: string; name: string }[]
  }[]
  projects: { id: number; code: string | null; name: string }[]
  cost_centres: { id: number; code: string; name: string }[]
}

export interface GrantReportRow {
  account_id: number
  account_code: string
  account_name: string
  dea_id: number | null
  dea_code: string | null
  dea_name: string | null
  budget_sof: number
  actual_sof: number
  actual_base: number
  variance_sof: number
  utilization_percent: number | null
}

export interface GrantReportTransaction {
  journal_entry_line_id: number
  date: string
  reference_number: string
  description: string | null
  account_id: number
  account_code: string
  account_name: string
  dea_id: number | null
  dea_code: string | null
  project_code: string | null
  cost_centre_code: string | null
  currency_code: string
  exchange_rate: number
  transaction_amount: number
  actual_base: number
  actual_sof: number
  is_income: boolean
}

export interface GrantReport {
  grant: Pick<Grant, 'id' | 'code' | 'name' | 'currency_code' | 'start_date' | 'end_date' | 'status'> & { donor: string | null }
  from: string
  to: string
  sof_currency: string
  base_currency: string
  rows: GrantReportRow[]
  totals: {
    award_sof: number
    budget_sof: number
    actual_sof: number
    actual_base: number
    variance_sof: number
    remaining_award_sof: number
    burn_percent: number | null
    time_elapsed_percent: number
    income_sof: number
    income_base: number
  }
  transactions: GrantReportTransaction[]
  missing_rates: string[]
  currency?: ReportCurrencyInfo
}
