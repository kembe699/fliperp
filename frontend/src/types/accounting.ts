import type { ReportCurrencyInfo } from '@/types/exchangeRate'

export type ChartOfAccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'

export interface ChartOfAccount {
  id: number
  company_id: number
  code: string
  name: string
  type: ChartOfAccountType
  parent_id: number | null
  is_active: boolean
  /** false for a heading: it groups other accounts and takes no postings. */
  is_postable?: boolean
  opening_balance?: number
  opening_balance_date?: string | null
  opening_balance_entry_id?: number | null
  balance?: number
}

export type JournalEntryStatus = 'draft' | 'posted' | 'reversed'

export interface JournalEntryLine {
  id: number
  account_id: number
  account_code: string | null
  account_name: string | null
  /** Base-currency amounts (what every report uses). */
  debit: number
  credit: number
  /** Amounts in the entry's own currency. */
  transaction_debit: number
  transaction_credit: number
  description: string | null
  grant_id?: number | null
  grant_code?: string | null
  dea_id?: number | null
  dea_code?: string | null
  project_id?: number | null
  project_code?: string | null
  cost_centre_id?: number | null
  cost_centre_code?: string | null
}

export interface JournalEntry {
  id: number
  company_id: number
  branch_id: number | null
  reference_number: string
  entry_date: string
  description: string | null
  source_module: string | null
  source_id: number | null
  posted_by: number | null
  status: JournalEntryStatus
  currency_code: string
  /** Units of currency_code per 1 base unit. */
  exchange_rate: number
  lines: JournalEntryLine[]
  total_debit: number | null
  total_credit: number | null
  total_transaction_debit: number | null
  total_transaction_credit: number | null
}

export type AccountingPeriodStatus = 'open' | 'closed'

export interface AccountingPeriod {
  id: number
  company_id: number
  name: string
  start_date: string
  end_date: string
  status: AccountingPeriodStatus
  closed_by: number | null
  closed_at: string | null
}

export interface ReportAccountLine {
  account_id: number
  code: string
  name: string
  amount: number
}

export interface TrialBalanceReport {
  currency?: ReportCurrencyInfo
  from: string
  to: string
  accounts: { account_id: number; code: string; name: string; type: string; debit: number; credit: number; balance: number }[]
  total_debit: number
  total_credit: number
}

export interface ProfitAndLossReport {
  currency?: ReportCurrencyInfo
  from: string
  to: string
  revenue: { total: number; accounts: ReportAccountLine[] }
  cogs: { total: number; accounts: ReportAccountLine[] }
  gross_profit: number
  operating_expenses: { total: number; accounts: ReportAccountLine[] }
  net_profit: number
}

export interface BalanceSheetReport {
  currency?: ReportCurrencyInfo
  as_of: string
  assets: { total: number; accounts: ReportAccountLine[] }
  liabilities: { total: number; accounts: ReportAccountLine[] }
  equity: { total: number; accounts: ReportAccountLine[]; net_income: number }
  total_liabilities_and_equity: number
}

export interface CashFlowReport {
  currency?: ReportCurrencyInfo
  from: string
  to: string
  categories: { source_module: string; cash_in: number; cash_out: number; net: number }[]
  net_cash_movement: number
}

export type ReportSnapshotType = 'trial-balance' | 'profit-and-loss' | 'balance-sheet' | 'general-ledger' | 'cash-flow'

export interface ReportSnapshot {
  id: number
  company_id: number
  report_type: ReportSnapshotType
  period_start: string
  period_end: string
  generated_by: number | null
  data: unknown
  generated_at: string
}

/** An automatic-posting function and the account the company uses for it (Accounting > System accounts). */
export interface SystemAccount {
  function: SystemAccountFunction
  label: string
  default_code: string
  account: Pick<ChartOfAccount, 'id' | 'code' | 'name' | 'type'> | null
}

export type SystemAccountFunction =
  | 'cash' | 'accounts_receivable' | 'inventory' | 'accumulated_depreciation' | 'accounts_payable'
  | 'statutory_deductions_payable' | 'net_salaries_payable' | 'tax_payable' | 'opening_balance_equity'
  | 'sales_revenue' | 'salary_expense' | 'depreciation_expense' | 'default_expense' | 'cost_of_sales'
  | 'cash_short_over' | 'fx_gain' | 'fx_loss'
