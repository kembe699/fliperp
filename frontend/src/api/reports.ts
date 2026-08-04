import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import { exportToCsv, type CsvColumn } from '@/lib/csv-export'
import type {
  BalanceSheetReport,
  CashFlowReport,
  ChartOfAccount,
  ProfitAndLossReport,
  ReportAccountLine,
  ReportSnapshot,
  ReportSnapshotType,
  TrialBalanceReport,
} from '@/types/accounting'

export type { ChartOfAccount }

export async function fetchChartOfAccounts(): Promise<ChartOfAccount[]> {
  const { data } = await api.get<PaginatedResponse<ChartOfAccount>>('/chart-of-accounts', {
    params: { per_page: 200 },
  })
  return data.data
}

export interface GeneralLedgerLine {
  date: string
  reference_number: string
  description: string | null
  debit: number
  credit: number
  balance: number
  source_module: string | null
  source_id: number | null
}

export interface GeneralLedgerReport {
  from: string
  to: string
  account: { account_id: number; code: string; name: string; type: string }
  opening_balance: number
  lines: GeneralLedgerLine[]
  closing_balance: number
}

export async function fetchGeneralLedger(params: {
  account_id: number
  from: string
  to: string
}): Promise<GeneralLedgerReport> {
  const { data } = await api.get<ApiResponse<GeneralLedgerReport>>('/reports/general-ledger', { params })
  return data.data
}

export async function fetchTrialBalance(params: { from: string; to: string; branch_id?: number }): Promise<TrialBalanceReport> {
  const { data } = await api.get<ApiResponse<TrialBalanceReport>>('/reports/trial-balance', { params })
  return data.data
}

export async function fetchProfitAndLoss(params: { from: string; to: string; branch_id?: number }): Promise<ProfitAndLossReport> {
  const { data } = await api.get<ApiResponse<ProfitAndLossReport>>('/reports/profit-and-loss', { params })
  return data.data
}

export async function fetchBalanceSheet(params: { as_of: string; branch_id?: number }): Promise<BalanceSheetReport> {
  const { data } = await api.get<ApiResponse<BalanceSheetReport>>('/reports/balance-sheet', { params })
  return data.data
}

export async function fetchCashFlow(params: { from: string; to: string; branch_id?: number }): Promise<CashFlowReport> {
  const { data } = await api.get<ApiResponse<CashFlowReport>>('/reports/cash-flow', { params })
  return data.data
}

export async function fetchReportSnapshots(params: { report_type?: ReportSnapshotType; page?: number; per_page?: number } = {}): Promise<PaginatedResponse<ReportSnapshot>> {
  const { data } = await api.get<PaginatedResponse<ReportSnapshot>>('/reports/snapshots', { params })
  return data
}

/** Saves a snapshot for a report already viewed with these params (same query shape the report endpoint itself takes). */
export async function saveReportSnapshot(type: ReportSnapshotType, params: Record<string, string | number>): Promise<ReportSnapshot> {
  const { data } = await api.post<ApiResponse<ReportSnapshot>>(`/reports/${type}/snapshot`, null, { params })
  return data.data
}

/** For the General Ledger report, which needs account_id, not just from/to. One row per ledger line. */
export async function downloadGeneralLedger(accountId: number, from: string, to: string): Promise<void> {
  const report = await fetchGeneralLedger({ account_id: accountId, from, to })

  const columns: CsvColumn<GeneralLedgerLine>[] = [
    { header: 'Date', accessor: (row) => row.date },
    { header: 'Reference', accessor: (row) => row.reference_number },
    { header: 'Description', accessor: (row) => row.description },
    { header: 'Debit', accessor: (row) => row.debit },
    { header: 'Credit', accessor: (row) => row.credit },
    { header: 'Balance', accessor: (row) => row.balance },
    { header: 'Source Module', accessor: (row) => row.source_module },
    { header: 'Source ID', accessor: (row) => row.source_id },
  ]

  const openingRow: GeneralLedgerLine = {
    date: report.from,
    reference_number: 'Opening Balance',
    description: null,
    debit: 0,
    credit: 0,
    balance: report.opening_balance,
    source_module: null,
    source_id: null,
  }

  exportToCsv(`general-ledger-${report.account.code}-${from}-to-${to}.csv`, columns, [openingRow, ...report.lines])
}

/** The 4 report types whose params reduce cleanly to from/to (or as_of for balance-sheet). */
export const QUICK_REPORT_TYPES = ['trial-balance', 'profit-and-loss', 'balance-sheet', 'cash-flow'] as const
export type QuickReportType = (typeof QUICK_REPORT_TYPES)[number]

export function quickReportLabel(type: QuickReportType): string {
  return {
    'trial-balance': 'Trial Balance',
    'profit-and-loss': 'Profit & Loss',
    'balance-sheet': 'Balance Sheet',
    'cash-flow': 'Cash Flow',
  }[type]
}

interface ReportCsvRow {
  section: string
  code: string
  name: string
  amount: number | string
}

const reportCsvColumns: CsvColumn<ReportCsvRow>[] = [
  { header: 'Section', accessor: (row) => row.section },
  { header: 'Code', accessor: (row) => row.code },
  { header: 'Account', accessor: (row) => row.name },
  { header: 'Amount', accessor: (row) => row.amount },
]

function accountRows(section: string, accounts: ReportAccountLine[]): ReportCsvRow[] {
  return accounts.map((account) => ({ section, code: account.code, name: account.name, amount: account.amount }))
}

/** Fetches a report and triggers a CSV download, one row per account/section, structured per report shape. */
export async function downloadReport(type: QuickReportType, from: string, to: string): Promise<void> {
  const filename = `${type}-${from}-to-${to}.csv`

  if (type === 'trial-balance') {
    const report = await fetchTrialBalance({ from, to })
    const columns: CsvColumn<TrialBalanceReport['accounts'][number]>[] = [
      { header: 'Code', accessor: (row) => row.code },
      { header: 'Account', accessor: (row) => row.name },
      { header: 'Type', accessor: (row) => row.type },
      { header: 'Debit', accessor: (row) => row.debit },
      { header: 'Credit', accessor: (row) => row.credit },
      { header: 'Balance', accessor: (row) => row.balance },
    ]
    exportToCsv(filename, columns, report.accounts)
    return
  }

  if (type === 'profit-and-loss') {
    const report = await fetchProfitAndLoss({ from, to })
    const rows: ReportCsvRow[] = [
      ...accountRows('Revenue', report.revenue.accounts),
      { section: 'Revenue', code: '', name: 'Total Revenue', amount: report.revenue.total },
      ...accountRows('Cost of Goods Sold', report.cogs.accounts),
      { section: 'Cost of Goods Sold', code: '', name: 'Total COGS', amount: report.cogs.total },
      { section: 'Summary', code: '', name: 'Gross Profit', amount: report.gross_profit },
      ...accountRows('Operating Expenses', report.operating_expenses.accounts),
      { section: 'Operating Expenses', code: '', name: 'Total Operating Expenses', amount: report.operating_expenses.total },
      { section: 'Summary', code: '', name: 'Net Profit', amount: report.net_profit },
    ]
    exportToCsv(filename, reportCsvColumns, rows)
    return
  }

  if (type === 'balance-sheet') {
    const report = await fetchBalanceSheet({ as_of: to })
    const rows: ReportCsvRow[] = [
      ...accountRows('Assets', report.assets.accounts),
      { section: 'Assets', code: '', name: 'Total Assets', amount: report.assets.total },
      ...accountRows('Liabilities', report.liabilities.accounts),
      { section: 'Liabilities', code: '', name: 'Total Liabilities', amount: report.liabilities.total },
      ...accountRows('Equity', report.equity.accounts),
      { section: 'Equity', code: '', name: 'Net Income (accumulated)', amount: report.equity.net_income },
      { section: 'Equity', code: '', name: 'Total Equity', amount: report.equity.total },
      { section: 'Summary', code: '', name: 'Total Liabilities + Equity', amount: report.total_liabilities_and_equity },
    ]
    exportToCsv(filename, reportCsvColumns, rows)
    return
  }

  const report = await fetchCashFlow({ from, to })
  const columns: CsvColumn<CashFlowReport['categories'][number]>[] = [
    { header: 'Category', accessor: (row) => row.source_module },
    { header: 'Cash In', accessor: (row) => row.cash_in },
    { header: 'Cash Out', accessor: (row) => row.cash_out },
    { header: 'Net', accessor: (row) => row.net },
  ]
  exportToCsv(filename, columns, [
    ...report.categories,
    { source_module: 'Net Cash Movement', cash_in: 0, cash_out: 0, net: report.net_cash_movement },
  ])
}
