export type ChartOfAccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense'

export interface ChartOfAccount {
  id: number
  company_id: number
  code: string
  name: string
  type: ChartOfAccountType
  parent_id: number | null
  is_active: boolean
}

export type JournalEntryStatus = 'draft' | 'posted' | 'reversed'

export interface JournalEntryLine {
  id: number
  account_id: number
  account_code: string | null
  account_name: string | null
  debit: number
  credit: number
  description: string | null
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
  lines: JournalEntryLine[]
  total_debit: number | null
  total_credit: number | null
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
  from: string
  to: string
  accounts: { account_id: number; code: string; name: string; type: string; debit: number; credit: number; balance: number }[]
  total_debit: number
  total_credit: number
}

export interface ProfitAndLossReport {
  from: string
  to: string
  revenue: { total: number; accounts: ReportAccountLine[] }
  cogs: { total: number; accounts: ReportAccountLine[] }
  gross_profit: number
  operating_expenses: { total: number; accounts: ReportAccountLine[] }
  net_profit: number
}

export interface BalanceSheetReport {
  as_of: string
  assets: { total: number; accounts: ReportAccountLine[] }
  liabilities: { total: number; accounts: ReportAccountLine[] }
  equity: { total: number; accounts: ReportAccountLine[]; net_income: number }
  total_liabilities_and_equity: number
}

export interface CashFlowReport {
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
