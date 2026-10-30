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
import type { ReportCurrencyInfo } from '@/types/exchangeRate'
import type { PayablesReport, ReceivablesReport } from '@/types/aging'
import type { Coding } from '@/types/grants'

/** SOF / DEA / project / cost-centre report filters. */
export type DimensionParams = Partial<Record<keyof Coding | 'department_id', number>>

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
  /** The line as transacted; debit/credit above are base-currency figures. */
  currency_code?: string | null
  exchange_rate?: number
  transaction_debit?: number
  transaction_credit?: number
  grant_code?: string | null
  dea_code?: string | null
  project_code?: string | null
  cost_centre_code?: string | null
}

export interface GeneralLedgerReport {
  from: string
  to: string
  account: { account_id: number; code: string; name: string; type: string }
  opening_balance: number
  lines: GeneralLedgerLine[]
  closing_balance: number
  currency?: ReportCurrencyInfo
}

export async function fetchGeneralLedger(params: {
  account_id: number
  from: string
  to: string
  currency?: string
} & DimensionParams): Promise<GeneralLedgerReport> {
  const { data } = await api.get<ApiResponse<GeneralLedgerReport>>('/reports/general-ledger', { params })
  return data.data
}

export interface GeneralLedgerAccount {
  account: GeneralLedgerReport['account']
  opening_balance: number
  lines: GeneralLedgerLine[]
  closing_balance: number
}

/** Every account with a balance or activity in the period, each with its own ledger. */
export interface GeneralLedgerAllReport {
  from: string
  to: string
  accounts: GeneralLedgerAccount[]
  currency?: ReportCurrencyInfo
}

export async function fetchGeneralLedgerAll(params: { from: string; to: string; currency?: string } & DimensionParams): Promise<GeneralLedgerAllReport> {
  const { data } = await api.get<ApiResponse<GeneralLedgerAllReport>>('/reports/general-ledger', { params })
  return data.data
}

export async function fetchTrialBalance(params: { from: string; to: string; branch_id?: number; currency?: string } & DimensionParams): Promise<TrialBalanceReport> {
  const { data } = await api.get<ApiResponse<TrialBalanceReport>>('/reports/trial-balance', { params })
  return data.data
}

export async function fetchProfitAndLoss(params: { from: string; to: string; branch_id?: number; currency?: string } & DimensionParams): Promise<ProfitAndLossReport> {
  const { data } = await api.get<ApiResponse<ProfitAndLossReport>>('/reports/profit-and-loss', { params })
  return data.data
}

export async function fetchBalanceSheet(params: { as_of: string; branch_id?: number; currency?: string }): Promise<BalanceSheetReport> {
  const { data } = await api.get<ApiResponse<BalanceSheetReport>>('/reports/balance-sheet', { params })
  return data.data
}

export async function fetchCashFlow(params: { from: string; to: string; branch_id?: number; currency?: string }): Promise<CashFlowReport> {
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

/** The General Ledger for every account: an opening row, then one row per ledger line, account by account. */
export async function downloadGeneralLedger(from: string, to: string, currency?: string, dimensions: DimensionParams = {}): Promise<void> {
  const report = await fetchGeneralLedgerAll({ from, to, currency, ...dimensions })

  type Row = GeneralLedgerLine & { account: string }
  const columns: CsvColumn<Row>[] = [
    { header: 'Account', accessor: (row) => row.account },
    { header: 'Date', accessor: (row) => row.date },
    { header: 'Reference', accessor: (row) => row.reference_number },
    { header: 'Description', accessor: (row) => row.description },
    { header: 'Debit', accessor: (row) => row.debit },
    { header: 'Credit', accessor: (row) => row.credit },
    { header: 'Balance', accessor: (row) => row.balance },
    { header: 'Source Module', accessor: (row) => row.source_module },
    { header: 'Source ID', accessor: (row) => row.source_id },
    { header: 'Transaction Currency', accessor: (row) => row.currency_code ?? '' },
    { header: 'Exchange Rate', accessor: (row) => row.exchange_rate ?? '' },
    { header: 'Transaction Debit', accessor: (row) => row.transaction_debit ?? '' },
    { header: 'Transaction Credit', accessor: (row) => row.transaction_credit ?? '' },
    { header: 'SOF', accessor: (row) => row.grant_code ?? '' },
    { header: 'DEA', accessor: (row) => row.dea_code ?? '' },
    { header: 'Project', accessor: (row) => row.project_code ?? '' },
    { header: 'Cost Centre', accessor: (row) => row.cost_centre_code ?? '' },
  ]

  const rows: Row[] = report.accounts.flatMap((ledger) => {
    const account = `${ledger.account.code} ${ledger.account.name}`
    const opening: Row = { account, date: report.from, reference_number: 'Opening Balance', description: null, debit: 0, credit: 0, balance: ledger.opening_balance, source_module: null, source_id: null }
    return [opening, ...ledger.lines.map((line) => ({ ...line, account }))]
  })

  exportToCsv(`general-ledger-${from}-to-${to}${csvCurrencySuffix(report.currency)}.csv`, columns, rows)
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

/** Converted downloads say so in the file name, e.g. "-in-SSP-converted". */
function csvCurrencySuffix(currency?: ReportCurrencyInfo): string {
  return currency?.converted ? `-in-${currency.code}-converted` : ''
}

/** Fetches a report and triggers a CSV download, one row per account/section, structured per report shape. */
export async function downloadReport(type: QuickReportType, from: string, to: string, currency?: string, dimensions: DimensionParams = {}): Promise<void> {
  const suffix = currency ? `-in-${currency}` : ''
  const filename = `${type}-${from}-to-${to}${suffix}.csv`

  if (type === 'trial-balance') {
    const report = await fetchTrialBalance({ from, to, currency, ...dimensions })
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
    const report = await fetchProfitAndLoss({ from, to, currency, ...dimensions })
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
    const report = await fetchBalanceSheet({ as_of: to, currency })
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

  const report = await fetchCashFlow({ from, to, currency })
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

export async function fetchReceivables(asOf?: string): Promise<ReceivablesReport> {
  const { data } = await api.get<ApiResponse<ReceivablesReport>>('/reports/receivables', { params: { as_of: asOf } })
  return data.data
}

export async function fetchPayables(asOf?: string): Promise<PayablesReport> {
  const { data } = await api.get<ApiResponse<PayablesReport>>('/reports/payables', { params: { as_of: asOf } })
  return data.data
}
