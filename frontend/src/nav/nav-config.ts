import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Truck,
  Users,
  Boxes,
  Route,
  PiggyBank,
  BarChart3,
  Calculator,
  Settings,
  type LucideIcon,
} from 'lucide-react'

export interface NavChild {
  label: string
  path: string
}

export interface NavItem {
  label: string
  path: string
  icon: LucideIcon
  children?: NavChild[]
}

export const navConfig: NavItem[] = [
  { label: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
  {
    label: 'Sales',
    path: '/sales',
    icon: ShoppingCart,
    children: [
      { label: 'POS', path: '/pos' },
      { label: 'Receipts', path: '/receipts' },
      { label: 'Invoices', path: '/invoices' },
      { label: 'Quotations', path: '/quotations' },
      { label: 'Customers', path: '/customers' },
    ],
  },
  {
    label: 'Inventory',
    path: '/inventory',
    icon: Package,
    children: [
      { label: 'Products', path: '/products' },
      { label: 'Categories', path: '/categories' },
      { label: 'Stock Transfers', path: '/stock-transfers' },
      { label: 'Stock Adjustments', path: '/stock-adjustments' },
      { label: 'Warehouses', path: '/warehouses' },
    ],
  },
  {
    label: 'Procurement',
    path: '/procurement',
    icon: Truck,
    children: [
      { label: 'Suppliers', path: '/suppliers' },
      { label: 'Purchase Orders', path: '/purchase-orders' },
      { label: 'Goods Received Notes', path: '/goods-received-notes' },
      { label: 'Supplier Bills', path: '/supplier-bills' },
    ],
  },
  {
    label: 'HR & Payroll',
    path: '/hr',
    icon: Users,
    children: [
      { label: 'Employees', path: '/employees' },
      { label: 'Attendance', path: '/attendance' },
      { label: 'Leave Requests', path: '/leave-requests' },
      { label: 'Payroll Runs', path: '/payroll-runs' },
      { label: 'Salary Structures', path: '/salary-structures' },
      { label: 'Statutory Deduction Rules', path: '/statutory-deduction-rules' },
    ],
  },
  {
    label: 'Assets',
    path: '/assets',
    icon: Boxes,
    children: [{ label: 'Asset Categories', path: '/asset-categories' }],
  },
  {
    label: 'Logistics',
    path: '/logistics',
    icon: Route,
    children: [
      { label: 'Vehicles', path: '/vehicles' },
      { label: 'Dispatches', path: '/dispatches' },
    ],
  },
  { label: 'Budgeting', path: '/budget-periods', icon: PiggyBank },
  { label: 'M&E', path: '/me-projects', icon: BarChart3 },
  {
    label: 'Accounting',
    path: '/accounting',
    icon: Calculator,
    children: [
      { label: 'Chart of Accounts', path: '/chart-of-accounts' },
      { label: 'Journal Entries', path: '/journal-entries' },
      { label: 'Accounting Periods', path: '/accounting-periods' },
      { label: 'Trial Balance', path: '/reports/trial-balance' },
      { label: 'Profit & Loss', path: '/reports/profit-and-loss' },
      { label: 'Balance Sheet', path: '/reports/balance-sheet' },
      { label: 'General Ledger', path: '/reports/general-ledger' },
      { label: 'Cash Flow', path: '/reports/cash-flow' },
    ],
  },
  {
    label: 'Settings',
    path: '/settings',
    icon: Settings,
    children: [
      { label: 'Company', path: '/settings/company' },
      { label: 'Branches', path: '/settings/branches' },
      { label: 'Users', path: '/settings/users' },
      { label: 'Roles', path: '/settings/roles' },
      { label: 'Tax Rates', path: '/settings/tax-rates' },
      { label: 'Payment Types', path: '/settings/payment-types' },
    ],
  },
]
