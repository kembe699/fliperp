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
  Contact,
  type LucideIcon,
} from 'lucide-react'

export interface NavChild {
  label: string
  path: string
  /** Matches the Spatie permission name each page's backend policy actually
   *  checks for viewAny() — e.g. "sales.view" for SalePolicy. Absent means
   *  always visible (no policy gate, e.g. Dashboard's own summary widgets
   *  are permission-gated per-widget rather than as a whole page). */
  requiredPermission?: string
}

export interface NavItem {
  label: string
  path: string
  icon: LucideIcon
  /** Only meaningful for leaf items (no children) — parent items with
   *  children are never directly clickable in the sidebar (they only
   *  expand/collapse), so their own visibility is derived from whether any
   *  child is visible, not from a permission on the parent itself. */
  requiredPermission?: string
  children?: NavChild[]
}

export const navConfig: NavItem[] = [
  { label: 'Dashboard', path: '/dashboard', icon: LayoutDashboard, requiredPermission: 'dashboard.view' },
  {
    label: 'Sales',
    path: '/sales',
    icon: ShoppingCart,
    children: [
      { label: 'POS', path: '/pos', requiredPermission: 'sales.create' },
      { label: 'Receipts', path: '/receipts', requiredPermission: 'sales.view' },
      { label: 'Invoices', path: '/invoices', requiredPermission: 'invoices.view' },
      { label: 'Quotations', path: '/quotations', requiredPermission: 'quotations.view' },
      { label: 'Customers', path: '/customers', requiredPermission: 'customers.view' },
    ],
  },
  {
    label: 'Inventory',
    path: '/inventory',
    icon: Package,
    children: [
      { label: 'Products', path: '/products', requiredPermission: 'products.view' },
      { label: 'Categories', path: '/categories', requiredPermission: 'categories.view' },
      { label: 'Stock Transfers', path: '/stock-transfers', requiredPermission: 'stock-transfers.view' },
      { label: 'Stock Adjustments', path: '/stock-adjustments', requiredPermission: 'stock-adjustments.view' },
      { label: 'Warehouses', path: '/warehouses', requiredPermission: 'warehouses.view' },
    ],
  },
  {
    label: 'Procurement',
    path: '/procurement',
    icon: Truck,
    children: [
      { label: 'Suppliers', path: '/suppliers', requiredPermission: 'suppliers.view' },
      { label: 'Purchase Orders', path: '/purchase-orders', requiredPermission: 'purchase-orders.view' },
      { label: 'Goods Received Notes', path: '/goods-received-notes', requiredPermission: 'goods-received-notes.view' },
      { label: 'Supplier Bills', path: '/supplier-bills', requiredPermission: 'supplier-bills.view' },
    ],
  },
  {
    label: 'HR & Payroll',
    path: '/hr',
    icon: Users,
    children: [
      { label: 'Employees', path: '/employees', requiredPermission: 'employees.view' },
      { label: 'Attendance', path: '/attendance', requiredPermission: 'attendance.view' },
      { label: 'Leave Requests', path: '/leave-requests', requiredPermission: 'leave-requests.view' },
      { label: 'Payroll Runs', path: '/payroll-runs', requiredPermission: 'payroll-runs.view' },
      { label: 'Salary Structures', path: '/salary-structures', requiredPermission: 'salary-structures.view' },
      { label: 'Statutory Deduction Rules', path: '/statutory-deduction-rules', requiredPermission: 'statutory-deduction-rules.view' },
    ],
  },
  {
    label: 'Assets',
    path: '/assets',
    icon: Boxes,
    children: [{ label: 'Asset Categories', path: '/asset-categories', requiredPermission: 'asset-categories.view' }],
  },
  {
    label: 'Logistics',
    path: '/logistics',
    icon: Route,
    children: [
      { label: 'Vehicles', path: '/vehicles', requiredPermission: 'vehicles.view' },
      { label: 'Dispatches', path: '/dispatches', requiredPermission: 'dispatches.view' },
    ],
  },
  {
    label: 'CRM',
    path: '/crm',
    icon: Contact,
    children: [
      { label: 'Dashboard', path: '/crm', requiredPermission: 'crm-reports.view' },
      { label: 'Leads', path: '/crm/leads', requiredPermission: 'crm-leads.view' },
      { label: 'Pipeline', path: '/crm/pipeline', requiredPermission: 'crm-deals.view' },
      { label: 'Customers', path: '/crm/customers', requiredPermission: 'crm-customer-services.view' },
      { label: 'Services', path: '/crm/services', requiredPermission: 'crm-services.view' },
      { label: 'Staff', path: '/crm/staff', requiredPermission: 'crm-reports.view' },
      { label: 'Reports', path: '/crm/reports', requiredPermission: 'crm-reports.view' },
    ],
  },
  { label: 'Budgeting', path: '/budget-periods', icon: PiggyBank, requiredPermission: 'budget-periods.view' },
  { label: 'M&E', path: '/me-projects', icon: BarChart3, requiredPermission: 'me-projects.view' },
  {
    label: 'Accounting',
    path: '/accounting',
    icon: Calculator,
    children: [
      { label: 'Chart of Accounts', path: '/chart-of-accounts', requiredPermission: 'chart-of-accounts.view' },
      { label: 'Journal Entries', path: '/journal-entries', requiredPermission: 'journal-entries.view' },
      { label: 'Accounting Periods', path: '/accounting-periods', requiredPermission: 'accounting-periods.view' },
      { label: 'Trial Balance', path: '/reports/trial-balance', requiredPermission: 'reports.view' },
      { label: 'Profit & Loss', path: '/reports/profit-and-loss', requiredPermission: 'reports.view' },
      { label: 'Balance Sheet', path: '/reports/balance-sheet', requiredPermission: 'reports.view' },
      { label: 'General Ledger', path: '/reports/general-ledger', requiredPermission: 'reports.view' },
      { label: 'Cash Flow', path: '/reports/cash-flow', requiredPermission: 'reports.view' },
      { label: 'Shift Report', path: '/reports/shift-report', requiredPermission: 'cash-drawer-sessions.view' },
    ],
  },
  {
    label: 'Settings',
    path: '/settings',
    icon: Settings,
    children: [
      { label: 'Company', path: '/settings/company', requiredPermission: 'companies.view' },
      { label: 'Branches', path: '/settings/branches', requiredPermission: 'branches.view' },
      { label: 'Users', path: '/settings/users', requiredPermission: 'users.view' },
      { label: 'Roles', path: '/settings/roles', requiredPermission: 'roles.view' },
      { label: 'Tax Rates', path: '/settings/tax-rates', requiredPermission: 'tax-rates.view' },
      { label: 'Payment Types', path: '/settings/payment-types', requiredPermission: 'payment-types.view' },
    ],
  },
]
