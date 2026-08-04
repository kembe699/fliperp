import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom'

import { navConfig } from '@/nav/nav-config'
import { AppLayout } from '@/components/layout/AppLayout'
import { ProtectedRoute } from '@/routes/ProtectedRoute'
import { PortalProtectedRoute } from '@/routes/PortalProtectedRoute'
import { PortalShell } from '@/components/portal/PortalShell'
import { LoginPage } from '@/pages/LoginPage'
import { ReceiptVerifyPage } from '@/pages/ReceiptVerifyPage'
import { PortalLoginPage } from '@/pages/portal/PortalLoginPage'
import { PortalDashboardPage } from '@/pages/portal/PortalDashboardPage'
import { PortalClockPage } from '@/pages/portal/PortalClockPage'
import { PortalLeavePage } from '@/pages/portal/PortalLeavePage'
import { DashboardPage } from '@/pages/DashboardPage'
import { NotificationsPage } from '@/pages/NotificationsPage'
import { ComingSoonPage } from '@/pages/ComingSoonPage'

import { CustomersListPage } from '@/pages/customers/CustomersListPage'
import { CustomerStatementPage } from '@/pages/customers/CustomerStatementPage'
import { PosTerminalPage } from '@/pages/pos/PosTerminalPage'
import { ReceiptsListPage } from '@/pages/receipts/ReceiptsListPage'
import { InvoicesListPage } from '@/pages/invoices/InvoicesListPage'
import { InvoiceFormPage } from '@/pages/invoices/InvoiceFormPage'
import { InvoiceDetailPage } from '@/pages/invoices/InvoiceDetailPage'
import { QuotationsListPage } from '@/pages/quotations/QuotationsListPage'
import { QuotationFormPage } from '@/pages/quotations/QuotationFormPage'
import { QuotationDetailPage } from '@/pages/quotations/QuotationDetailPage'

import { ProductsListPage } from '@/pages/inventory/ProductsListPage'
import { ProductDetailPage } from '@/pages/inventory/ProductDetailPage'
import { CategoriesPage } from '@/pages/inventory/CategoriesPage'
import { WarehousesPage } from '@/pages/inventory/WarehousesPage'
import { StockTransfersListPage } from '@/pages/inventory/StockTransfersListPage'
import { StockTransferDetailPage } from '@/pages/inventory/StockTransferDetailPage'
import { StockAdjustmentsListPage } from '@/pages/inventory/StockAdjustmentsListPage'
import { StockAdjustmentDetailPage } from '@/pages/inventory/StockAdjustmentDetailPage'

import { SuppliersListPage } from '@/pages/procurement/SuppliersListPage'
import { SupplierStatementPage } from '@/pages/procurement/SupplierStatementPage'
import { SupplierBillsListPage } from '@/pages/procurement/SupplierBillsListPage'
import { SupplierBillDetailPage } from '@/pages/procurement/SupplierBillDetailPage'
import { SupplierBillFormPage } from '@/pages/procurement/SupplierBillFormPage'
import { PurchaseOrdersListPage } from '@/pages/procurement/PurchaseOrdersListPage'
import { PurchaseOrderFormPage } from '@/pages/procurement/PurchaseOrderFormPage'
import { PurchaseOrderDetailPage } from '@/pages/procurement/PurchaseOrderDetailPage'
import { GoodsReceivedNotesListPage } from '@/pages/procurement/GoodsReceivedNotesListPage'
import { GrnFormPage } from '@/pages/procurement/GrnFormPage'
import { GrnDetailPage } from '@/pages/procurement/GrnDetailPage'

import { EmployeesListPage } from '@/pages/hr/EmployeesListPage'
import { EmployeeDetailPage } from '@/pages/hr/EmployeeDetailPage'
import { AttendancePage } from '@/pages/hr/AttendancePage'
import { LeaveRequestsPage } from '@/pages/hr/LeaveRequestsPage'
import { PayrollRunsListPage } from '@/pages/hr/PayrollRunsListPage'
import { PayrollRunDetailPage } from '@/pages/hr/PayrollRunDetailPage'
import { SalaryStructuresPage } from '@/pages/hr/SalaryStructuresPage'
import { StatutoryDeductionRulesPage } from '@/pages/hr/StatutoryDeductionRulesPage'

import { AssetsListPage } from '@/pages/assets/AssetsListPage'
import { AssetDetailPage } from '@/pages/assets/AssetDetailPage'
import { AssetCategoriesPage } from '@/pages/assets/AssetCategoriesPage'

import { VehiclesPage } from '@/pages/logistics/VehiclesPage'
import { DispatchesListPage } from '@/pages/logistics/DispatchesListPage'
import { DispatchDetailPage } from '@/pages/logistics/DispatchDetailPage'

import { BudgetPeriodsListPage } from '@/pages/budgeting/BudgetPeriodsListPage'
import { BudgetPeriodDetailPage } from '@/pages/budgeting/BudgetPeriodDetailPage'

import { MeProjectsListPage } from '@/pages/me/MeProjectsListPage'
import { MeProjectDetailPage } from '@/pages/me/MeProjectDetailPage'

import { ChartOfAccountsPage } from '@/pages/accounting/ChartOfAccountsPage'
import { JournalEntriesListPage } from '@/pages/accounting/JournalEntriesListPage'
import { JournalEntryFormPage } from '@/pages/accounting/JournalEntryFormPage'
import { JournalEntryDetailPage } from '@/pages/accounting/JournalEntryDetailPage'
import { AccountingPeriodsPage } from '@/pages/accounting/AccountingPeriodsPage'
import { TrialBalanceReportPage } from '@/pages/accounting/TrialBalanceReportPage'
import { ProfitAndLossReportPage } from '@/pages/accounting/ProfitAndLossReportPage'
import { BalanceSheetReportPage } from '@/pages/accounting/BalanceSheetReportPage'
import { GeneralLedgerReportPage } from '@/pages/accounting/GeneralLedgerReportPage'
import { CashFlowReportPage } from '@/pages/accounting/CashFlowReportPage'
import { ShiftReportPage } from '@/pages/accounting/ShiftReportPage'

import { CompanySettingsPage } from '@/pages/settings/CompanySettingsPage'
import { BranchesSettingsPage } from '@/pages/settings/BranchesSettingsPage'
import { UsersSettingsPage } from '@/pages/settings/UsersSettingsPage'
import { RolesSettingsPage } from '@/pages/settings/RolesSettingsPage'
import { TaxRatesSettingsPage } from '@/pages/settings/TaxRatesSettingsPage'
import { PaymentTypesSettingsPage } from '@/pages/settings/PaymentTypesSettingsPage'

interface PlaceholderRoute {
  path: string
  title: string
  parent?: string
}

// These are now real pages, built out in this batch — excluded from the
// auto-generated "coming soon" placeholder list below.
const IMPLEMENTED_PATHS = new Set([
  '/dashboard',
  '/customers',
  '/pos',
  '/receipts',
  '/invoices',
  '/quotations',
  '/products',
  '/categories',
  '/warehouses',
  '/stock-transfers',
  '/stock-adjustments',
  '/suppliers',
  '/purchase-orders',
  '/goods-received-notes',
  '/supplier-bills',
  '/employees',
  '/attendance',
  '/leave-requests',
  '/payroll-runs',
  '/salary-structures',
  '/statutory-deduction-rules',
  '/assets',
  '/asset-categories',
  '/vehicles',
  '/dispatches',
  '/budget-periods',
  '/me-projects',
  '/chart-of-accounts',
  '/journal-entries',
  '/accounting-periods',
  '/reports/trial-balance',
  '/reports/profit-and-loss',
  '/reports/balance-sheet',
  '/reports/general-ledger',
  '/reports/cash-flow',
  '/reports/shift-report',
  '/settings/company',
  '/settings/branches',
  '/settings/users',
  '/settings/roles',
  '/settings/tax-rates',
  '/settings/payment-types',
])

const placeholderRoutes: PlaceholderRoute[] = navConfig
  .filter((item) => item.path !== '/dashboard')
  .flatMap((item) => [
    { path: item.path, title: item.label },
    ...(item.children?.map((child) => ({ path: child.path, title: child.label, parent: item.label })) ?? []),
  ])
  .filter((route) => !IMPLEMENTED_PATHS.has(route.path))

function App() {
  return (
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/verify/:id" element={<ReceiptVerifyPage />} />

        <Route path="/employee-portal/login" element={<PortalLoginPage />} />
        <Route
          element={
            <PortalProtectedRoute>
              <PortalShell>
                <Outlet />
              </PortalShell>
            </PortalProtectedRoute>
          }
        >
          <Route path="/employee-portal" element={<PortalDashboardPage />} />
          <Route path="/employee-portal/clock" element={<PortalClockPage />} />
          <Route path="/employee-portal/leave" element={<PortalLeavePage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/notifications" element={<NotificationsPage />} />

          <Route path="/customers" element={<CustomersListPage />} />
          <Route path="/customers/:id/statement" element={<CustomerStatementPage />} />

          <Route path="/pos" element={<PosTerminalPage />} />
          <Route path="/receipts" element={<ReceiptsListPage />} />

          <Route path="/invoices" element={<InvoicesListPage />} />
          <Route path="/invoices/new" element={<InvoiceFormPage />} />
          <Route path="/invoices/:id/edit" element={<InvoiceFormPage />} />
          <Route path="/invoices/:id" element={<InvoiceDetailPage />} />

          <Route path="/quotations" element={<QuotationsListPage />} />
          <Route path="/quotations/new" element={<QuotationFormPage />} />
          <Route path="/quotations/:id/edit" element={<QuotationFormPage />} />
          <Route path="/quotations/:id" element={<QuotationDetailPage />} />

          <Route path="/products" element={<ProductsListPage />} />
          <Route path="/products/:id" element={<ProductDetailPage />} />
          <Route path="/categories" element={<CategoriesPage />} />
          <Route path="/warehouses" element={<WarehousesPage />} />
          <Route path="/stock-transfers" element={<StockTransfersListPage />} />
          <Route path="/stock-transfers/:id" element={<StockTransferDetailPage />} />
          <Route path="/stock-adjustments" element={<StockAdjustmentsListPage />} />
          <Route path="/stock-adjustments/:id" element={<StockAdjustmentDetailPage />} />

          <Route path="/suppliers" element={<SuppliersListPage />} />
          <Route path="/suppliers/:id/statement" element={<SupplierStatementPage />} />
          <Route path="/purchase-orders" element={<PurchaseOrdersListPage />} />
          <Route path="/purchase-orders/new" element={<PurchaseOrderFormPage />} />
          <Route path="/purchase-orders/:id" element={<PurchaseOrderDetailPage />} />
          <Route path="/goods-received-notes" element={<GoodsReceivedNotesListPage />} />
          <Route path="/goods-received-notes/new" element={<GrnFormPage />} />
          <Route path="/goods-received-notes/:id" element={<GrnDetailPage />} />
          <Route path="/supplier-bills" element={<SupplierBillsListPage />} />
          <Route path="/supplier-bills/new" element={<SupplierBillFormPage />} />
          <Route path="/supplier-bills/:id" element={<SupplierBillDetailPage />} />

          <Route path="/employees" element={<EmployeesListPage />} />
          <Route path="/employees/:id" element={<EmployeeDetailPage />} />
          <Route path="/attendance" element={<AttendancePage />} />
          <Route path="/leave-requests" element={<LeaveRequestsPage />} />
          <Route path="/payroll-runs" element={<PayrollRunsListPage />} />
          <Route path="/payroll-runs/:id" element={<PayrollRunDetailPage />} />
          <Route path="/salary-structures" element={<SalaryStructuresPage />} />
          <Route path="/statutory-deduction-rules" element={<StatutoryDeductionRulesPage />} />

          <Route path="/assets" element={<AssetsListPage />} />
          <Route path="/assets/:id" element={<AssetDetailPage />} />
          <Route path="/asset-categories" element={<AssetCategoriesPage />} />

          <Route path="/vehicles" element={<VehiclesPage />} />
          <Route path="/dispatches" element={<DispatchesListPage />} />
          <Route path="/dispatches/:id" element={<DispatchDetailPage />} />

          <Route path="/budget-periods" element={<BudgetPeriodsListPage />} />
          <Route path="/budget-periods/:id" element={<BudgetPeriodDetailPage />} />

          <Route path="/me-projects" element={<MeProjectsListPage />} />
          <Route path="/me-projects/:id" element={<MeProjectDetailPage />} />

          <Route path="/chart-of-accounts" element={<ChartOfAccountsPage />} />
          <Route path="/journal-entries" element={<JournalEntriesListPage />} />
          <Route path="/journal-entries/new" element={<JournalEntryFormPage />} />
          <Route path="/journal-entries/:id" element={<JournalEntryDetailPage />} />
          <Route path="/accounting-periods" element={<AccountingPeriodsPage />} />
          <Route path="/reports/trial-balance" element={<TrialBalanceReportPage />} />
          <Route path="/reports/profit-and-loss" element={<ProfitAndLossReportPage />} />
          <Route path="/reports/balance-sheet" element={<BalanceSheetReportPage />} />
          <Route path="/reports/general-ledger" element={<GeneralLedgerReportPage />} />
          <Route path="/reports/cash-flow" element={<CashFlowReportPage />} />
          <Route path="/reports/shift-report" element={<ShiftReportPage />} />

          <Route path="/settings/company" element={<CompanySettingsPage />} />
          <Route path="/settings/branches" element={<BranchesSettingsPage />} />
          <Route path="/settings/users" element={<UsersSettingsPage />} />
          <Route path="/settings/roles" element={<RolesSettingsPage />} />
          <Route path="/settings/tax-rates" element={<TaxRatesSettingsPage />} />
          <Route path="/settings/payment-types" element={<PaymentTypesSettingsPage />} />

          {placeholderRoutes.map((route) => (
            <Route
              key={route.path}
              path={route.path}
              element={<ComingSoonPage title={route.title} parent={route.parent} />}
            />
          ))}
        </Route>

        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
