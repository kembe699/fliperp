import type { ComponentType } from 'react'
import type { LucideIcon } from 'lucide-react'
import {
  Activity,
  BookOpen,
  Boxes,
  ClipboardList,
  Coins,
  FileBarChart,
  HandCoins,
  LayoutDashboard,
  Landmark,
  PackageSearch,
  Receipt,
  Scale,
  ShoppingCart,
  TrendingDown,
  TrendingUp,
  Truck,
  Users,
  Wallet,
  Waves,
} from 'lucide-react'

import { TrialBalanceReportPage } from '@/pages/accounting/TrialBalanceReportPage'
import { ProfitAndLossReportPage } from '@/pages/accounting/ProfitAndLossReportPage'
import { BalanceSheetReportPage } from '@/pages/accounting/BalanceSheetReportPage'
import { GeneralLedgerReportPage } from '@/pages/accounting/GeneralLedgerReportPage'
import { CashFlowReportPage } from '@/pages/accounting/CashFlowReportPage'
import { ShiftReportPage } from '@/pages/accounting/ShiftReportPage'
import { ReceivablesPage } from '@/pages/accounting/ReceivablesPage'
import { PayablesPage } from '@/pages/accounting/PayablesPage'

export interface Option {
  value: string
  label: string
}

interface Base {
  id: string
  label: string
  desc: string
  group: string
  icon: LucideIcon
  /** Permission needed to see it in the sidebar (reports.view when not set). */
  permission?: string
}

/** A report served by /reports/center/{report}: filters plus one table. */
export interface CenterEntry extends Base {
  kind: 'center'
  report: string
  groupBy?: Option[]
  source?: Option[]
  status?: { label: string; options: Option[] }
  /** Stock on hand is "as at now": no date range. */
  noDates?: boolean
}

/** An existing accounting report page, shown inside the Reports Center. */
export interface PageEntry extends Base {
  kind: 'page'
  component: ComponentType
}

export type CatalogEntry = CenterEntry | PageEntry

export const GROUPS = ['Overview', 'Revenue & Sales', 'Expenses & Purchases', 'Inventory', 'Accounting', 'Human Resources', 'Administration']

const SOURCES: Option[] = [
  { value: 'all', label: 'POS sales and invoices' },
  { value: 'pos', label: 'POS sales only' },
  { value: 'invoice', label: 'Invoices only' },
]

export const CATALOG: CatalogEntry[] = [
  { kind: 'center', id: 'overview', report: 'overview', label: 'Business Overview', desc: 'Revenue, profit, cash, what is owed, and the trend', group: 'Overview', icon: LayoutDashboard },

  {
    kind: 'center', id: 'revenue-accounts', report: 'revenue-accounts', label: 'Revenue by Account', desc: 'Every revenue account in the chart of accounts and what it earned', group: 'Revenue & Sales', icon: Landmark,
    groupBy: [{ value: 'account', label: 'By account' }, { value: 'month', label: 'By account and month' }],
  },
  {
    kind: 'center', id: 'revenue', report: 'revenue', label: 'Sales & Revenue', desc: 'Revenue earned from POS sales and invoices, net of tax', group: 'Revenue & Sales', icon: TrendingUp,
    groupBy: [
      { value: 'customer', label: 'By customer' }, { value: 'product', label: 'By product' }, { value: 'category', label: 'By category' },
      { value: 'branch', label: 'By branch' }, { value: 'staff', label: 'By staff member' }, { value: 'day', label: 'By day' },
      { value: 'month', label: 'By month' }, { value: 'document', label: 'Every sale and invoice' },
    ],
    source: SOURCES,
  },
  {
    kind: 'center', id: 'collections', report: 'collections', label: 'Collections', desc: 'Money received from POS sales and invoice payments', group: 'Revenue & Sales', icon: HandCoins,
    groupBy: [
      { value: 'method', label: 'By payment method' }, { value: 'staff', label: 'By staff member' }, { value: 'customer', label: 'By customer' },
      { value: 'day', label: 'By day' }, { value: 'month', label: 'By month' }, { value: 'document', label: 'Every payment' },
    ],
    source: SOURCES,
  },
  { kind: 'page', id: 'receivables', label: 'Accounts Receivable', desc: 'Open invoices aged by customer', group: 'Revenue & Sales', icon: Receipt, component: ReceivablesPage },
  { kind: 'page', id: 'shift-report', label: 'Shift Report', desc: 'Cash drawer sessions and variances', group: 'Revenue & Sales', icon: Wallet, component: ShiftReportPage, permission: 'cash-drawer-sessions.view' },

  {
    kind: 'center', id: 'expense-accounts', report: 'expense-accounts', label: 'Expenses by Account', desc: 'Every expense account, cost of sales and operating costs', group: 'Expenses & Purchases', icon: TrendingDown,
    groupBy: [{ value: 'account', label: 'By account' }, { value: 'month', label: 'By account and month' }],
  },
  {
    kind: 'center', id: 'purchases', report: 'purchases', label: 'Purchases (Supplier Bills)', desc: 'What was bought, from whom, and what is still owed', group: 'Expenses & Purchases', icon: ShoppingCart,
    groupBy: [{ value: 'supplier', label: 'By supplier' }, { value: 'status', label: 'By status' }, { value: 'month', label: 'By month' }, { value: 'document', label: 'Every bill' }],
    status: { label: 'Status', options: [{ value: 'unpaid', label: 'Unpaid' }, { value: 'partially_paid', label: 'Partly paid' }, { value: 'paid', label: 'Paid' }, { value: 'overdue', label: 'Overdue' }] },
  },
  {
    kind: 'center', id: 'supplier-payments', report: 'supplier-payments', label: 'Supplier Payments', desc: 'Money paid to suppliers', group: 'Expenses & Purchases', icon: Coins,
    groupBy: [{ value: 'supplier', label: 'By supplier' }, { value: 'method', label: 'By payment method' }, { value: 'staff', label: 'By staff member' }, { value: 'month', label: 'By month' }, { value: 'document', label: 'Every payment' }],
  },
  { kind: 'page', id: 'payables', label: 'Accounts Payable', desc: 'Open supplier bills aged by supplier', group: 'Expenses & Purchases', icon: Truck, component: PayablesPage },

  {
    kind: 'center', id: 'stock', report: 'stock', label: 'Stock on Hand & Valuation', desc: 'What is in stock now, at cost and at selling price', group: 'Inventory', icon: Boxes, noDates: true,
    groupBy: [{ value: 'product', label: 'By product' }, { value: 'warehouse', label: 'By warehouse' }, { value: 'category', label: 'By category' }],
    status: { label: 'Show', options: [{ value: 'low', label: 'At or below reorder level' }] },
  },
  {
    kind: 'center', id: 'stock-movements', report: 'stock-movements', label: 'Stock Movements', desc: 'Stock in and out: purchases, sales, adjustments, transfers', group: 'Inventory', icon: PackageSearch,
    groupBy: [{ value: 'product', label: 'By product' }, { value: 'type', label: 'By movement type' }, { value: 'warehouse', label: 'By warehouse' }, { value: 'document', label: 'Every movement' }],
    status: {
      label: 'Movement',
      options: [
        { value: 'purchase', label: 'Purchase' }, { value: 'sale', label: 'Sale' }, { value: 'adjustment', label: 'Adjustment' },
        { value: 'transfer_in', label: 'Transfer in' }, { value: 'transfer_out', label: 'Transfer out' }, { value: 'return', label: 'Return' },
      ],
    },
  },

  { kind: 'page', id: 'profit-and-loss', label: 'Profit & Loss', desc: 'Income statement for the period', group: 'Accounting', icon: FileBarChart, component: ProfitAndLossReportPage },
  { kind: 'page', id: 'balance-sheet', label: 'Balance Sheet', desc: 'Assets, liabilities and equity at a date', group: 'Accounting', icon: Scale, component: BalanceSheetReportPage },
  { kind: 'page', id: 'trial-balance', label: 'Trial Balance', desc: 'Every account balance, debits against credits', group: 'Accounting', icon: ClipboardList, component: TrialBalanceReportPage },
  { kind: 'page', id: 'general-ledger', label: 'General Ledger', desc: 'Every posting to an account', group: 'Accounting', icon: BookOpen, component: GeneralLedgerReportPage },
  { kind: 'page', id: 'cash-flow', label: 'Cash Flow', desc: 'Cash in and out by activity', group: 'Accounting', icon: Waves, component: CashFlowReportPage },

  {
    kind: 'center', id: 'payroll', report: 'payroll', label: 'Payroll Summary', desc: 'Gross pay, deductions and net pay of processed payroll runs', group: 'Human Resources', icon: Users,
    groupBy: [{ value: 'employee', label: 'By employee' }, { value: 'department', label: 'By department' }, { value: 'run', label: 'By payroll run' }],
  },

  {
    kind: 'center', id: 'audit', report: 'audit', label: 'Staff Activity / Audit Log', desc: 'Who did what, and when', group: 'Administration', icon: Activity,
    groupBy: [{ value: 'document', label: 'Every action' }, { value: 'user', label: 'By user' }, { value: 'module', label: 'By module' }, { value: 'action', label: 'By action' }],
  },
]

export const findEntry = (id: string | undefined) => CATALOG.find((entry) => entry.id === id)
