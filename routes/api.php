<?php

use App\Http\Controllers\Api\AccountingPeriodController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceGeofenceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BudgetLineController;
use App\Http\Controllers\Api\BudgetPeriodController;
use App\Http\Controllers\Api\CashDrawerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChartOfAccountController;
use App\Http\Controllers\Api\ChatAttachmentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DispatchController;
use App\Http\Controllers\Api\EmployeeContractController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeePortalAuthController;
use App\Http\Controllers\Api\EmployeePortalController;
use App\Http\Controllers\Api\EmployeePortalLeaveRequestController;
use App\Http\Controllers\Api\GoodsReceivedNoteController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\MeActivityController;
use App\Http\Controllers\Api\MeIndicatorController;
use App\Http\Controllers\Api\MeProjectController;
use App\Http\Controllers\Api\MeResultController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentTypeController;
use App\Http\Controllers\Api\PayrollRunController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PosReportController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PriceListItemController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicSaleController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RestaurantTableController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalaryStructureController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StatutoryDeductionRuleController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\SupplierBillController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\UnitOfMeasureController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register-company', [AuthController::class, 'registerCompany']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Employee Self-Service Portal: a separate, restricted auth flow — the
    // "employee" role has no Spatie module permissions, every action here
    // is scoped to the acting user's own linked employee record instead.
    Route::prefix('employee-portal')->group(function () {
        Route::post('login', [EmployeePortalAuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'role:employee'])->group(function () {
            Route::post('logout', [EmployeePortalAuthController::class, 'logout']);
            Route::get('me', [EmployeePortalController::class, 'me']);
            Route::get('leave-summary', [EmployeePortalController::class, 'leaveSummary']);
            Route::get('leave-types', [EmployeePortalController::class, 'leaveTypes']);
            Route::post('check-location', [EmployeePortalController::class, 'checkLocation']);
            Route::post('clock-in', [EmployeePortalController::class, 'clockIn']);
            Route::post('clock-out', [EmployeePortalController::class, 'clockOut']);
            Route::get('leave-requests', [EmployeePortalLeaveRequestController::class, 'index']);
            Route::post('leave-requests', [EmployeePortalLeaveRequestController::class, 'store']);
        });
    });

    // Unauthenticated — backs the QR code printed on receipts, scanned by a
    // customer's own phone with no login of any kind.
    Route::prefix('public')->group(function () {
        Route::get('sales/{sale}/verify', [PublicSaleController::class, 'verify']);
    });

    // Also unauthenticated: non-sensitive reference data, and the public
    // receipt-verification page (no login) needs it to render amounts
    // with the right symbol instead of falling back to a raw currency code.
    Route::get('currencies', [CurrencyController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        // Echo's default authorizer is cookie/session based; this app is a
        // bearer-token SPA, so the channel-auth request must go through
        // auth:sanctum like every other endpoint instead of Broadcast::
        // routes()'s 'web'-middleware default (see frontend/src/lib/echo.ts
        // for the matching client-side authorizer override).
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

        Route::post('chat/conversations/with/{userId}', [ChatController::class, 'startConversation']);
        Route::get('chat/conversations', [ChatController::class, 'index']);
        Route::get('chat/conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
        Route::post('chat/conversations/{conversation}/read', [ChatController::class, 'markRead']);
        Route::get('chat/attachments/{attachment}/download', [ChatAttachmentController::class, 'download']);

        Route::apiResource('companies', CompanyController::class);
        Route::post('company/logo', [CompanyController::class, 'uploadLogo']);
        Route::delete('company/logo', [CompanyController::class, 'deleteLogo']);
        Route::apiResource('branches', BranchController::class);
        Route::apiResource('users', UserController::class);

        Route::middleware('permission:roles.view')->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('roles/{role}', [RoleController::class, 'show']);
        });
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

        Route::middleware('permission:permissions.view')->group(function () {
            Route::get('permissions', [PermissionController::class, 'index']);
            Route::get('permissions/{permission}', [PermissionController::class, 'show']);
        });
        Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.assign');
        Route::put('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.assign');
        Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.assign');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.assign');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs.view');

        // Finance core
        Route::apiResource('chart-of-accounts', ChartOfAccountController::class);
        Route::apiResource('journal-entries', JournalEntryController::class);
        Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post']);
        Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse']);

        // HR & Payroll
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('positions', PositionController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::post('employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto']);
        Route::delete('employees/{employee}/photo', [EmployeeController::class, 'deletePhoto']);
        Route::post('employees/{employee}/create-portal-account', [EmployeeController::class, 'createPortalAccount']);
        Route::apiResource('employee-contracts', EmployeeContractController::class);
        Route::post('employee-contracts/{employee_contract}/upload-document', [EmployeeContractController::class, 'uploadDocument']);
        Route::post('employee-contracts/{employee_contract}/sign', [EmployeeContractController::class, 'sign']);
        Route::get('employee-contracts/{employee_contract}/pdf', [EmployeeContractController::class, 'pdf']);
        Route::apiResource('attendance', AttendanceController::class);
        Route::apiResource('attendance-geofences', AttendanceGeofenceController::class);
        Route::post('attendance-geofences/{branch}/regenerate-qr-token', [AttendanceGeofenceController::class, 'regenerateQrToken']);
        Route::get('attendance-geofences/{branch}/qr-code', [AttendanceGeofenceController::class, 'qrCode']);
        Route::apiResource('leave-types', LeaveTypeController::class);
        Route::apiResource('leave-requests', LeaveRequestController::class);
        Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
        Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject']);

        Route::get('payroll-runs', [PayrollRunController::class, 'index']);
        Route::post('payroll-runs', [PayrollRunController::class, 'store']);
        Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show']);
        Route::post('payroll-runs/{payroll_run}/process', [PayrollRunController::class, 'process']);
        Route::get('payroll-runs/{payroll_run}/payslips', [PayrollRunController::class, 'payslips']);

        Route::apiResource('salary-structures', SalaryStructureController::class);
        Route::apiResource('statutory-deduction-rules', StatutoryDeductionRuleController::class);

        // Assets
        Route::apiResource('asset-categories', AssetCategoryController::class);
        Route::apiResource('assets', AssetController::class);
        Route::post('assets/run-depreciation', [AssetController::class, 'runDepreciation']);
        Route::post('assets/{asset}/assign', [AssetController::class, 'assign']);
        Route::post('assets/{asset}/dispose', [AssetController::class, 'dispose']);
        Route::get('assets/{asset}/maintenance-logs', [AssetController::class, 'maintenanceLogs']);
        Route::post('assets/{asset}/maintenance-logs', [AssetController::class, 'storeMaintenanceLog']);
        Route::get('assets/{asset}/depreciation-schedules', [AssetController::class, 'depreciationSchedules']);

        // Logistics
        Route::apiResource('vehicles', VehicleController::class);
        Route::apiResource('dispatches', DispatchController::class);
        Route::post('dispatches/{dispatch}/track', [DispatchController::class, 'track']);

        // Budgeting
        Route::apiResource('budget-periods', BudgetPeriodController::class);
        Route::get('budget-periods/{budget_period}/vs-actual', [BudgetPeriodController::class, 'vsActual']);
        Route::apiResource('budget-lines', BudgetLineController::class);

        // M&E
        Route::apiResource('me-projects', MeProjectController::class);
        Route::get('me-projects/{me_project}/dashboard', [MeProjectController::class, 'dashboard']);
        Route::apiResource('me-indicators', MeIndicatorController::class);
        Route::apiResource('me-activities', MeActivityController::class);
        Route::apiResource('me-results', MeResultController::class);

        // Products & Inventory
        Route::apiResource('categories', CategoryController::class);
        // "units-of-measure" singularizes to "units_of_measure" (Laravel only
        // singularizes the trailing word, and "measure" is already singular),
        // which wouldn't match the $unitOfMeasure controller parameter.
        Route::apiResource('units-of-measure', UnitOfMeasureController::class)
            ->parameters(['units-of-measure' => 'unit_of_measure']);

        Route::apiResource('products', ProductController::class);
        Route::get('products/{product}/stock-movements', [ProductController::class, 'stockMovements']);
        Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);
        Route::delete('products/{product}/image', [ProductController::class, 'deleteImage']);
        Route::apiResource('products.variants', ProductVariantController::class);

        Route::apiResource('warehouses', WarehouseController::class);

        Route::get('stock-levels', [StockLevelController::class, 'index'])->middleware('permission:stock-levels.view');

        Route::post('stock-movements/manual', [StockMovementController::class, 'manual'])->middleware('permission:stock-movements.manual');

        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('stock-transfers/{stock_transfer}/mark-in-transit', [StockTransferController::class, 'markInTransit']);
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete'])->middleware('permission:stock-transfers.complete');
        Route::post('stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel']);

        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::post('stock-adjustments/{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve'])->middleware('permission:stock-adjustments.approve');

        // Procurement & GRN
        Route::apiResource('suppliers', SupplierController::class);
        Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement']);

        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit'])->middleware('permission:purchase-orders.submit');
        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:purchase-orders.approve');
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchase-orders.cancel');
        Route::get('purchase-orders/{purchase_order}/receiving-status', [PurchaseOrderController::class, 'receivingStatus']);
        Route::get('purchase-orders/{purchase_order}/pdf', [PurchaseOrderController::class, 'pdf']);
        Route::post('purchase-orders/{purchase_order}/email', [PurchaseOrderController::class, 'email']);

        Route::apiResource('goods-received-notes', GoodsReceivedNoteController::class);
        Route::post('goods-received-notes/{goods_received_note}/confirm', [GoodsReceivedNoteController::class, 'confirm'])->middleware('permission:goods-received-notes.confirm');
        Route::get('goods-received-notes/{goods_received_note}/pdf', [GoodsReceivedNoteController::class, 'pdf']);

        Route::apiResource('supplier-bills', SupplierBillController::class);
        Route::get('supplier-bills/{supplier_bill}/pdf', [SupplierBillController::class, 'pdf']);
        Route::apiResource('supplier-payments', SupplierPaymentController::class);

        // POS: Tax Rates, Payment Types, Customers, Cash Drawer, Sales
        Route::apiResource('tax-rates', TaxRateController::class);
        Route::apiResource('payment-types', PaymentTypeController::class);

        Route::apiResource('customers', CustomerController::class);
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement']);
        Route::get('customers/{customer}/statement/pdf', [CustomerController::class, 'statementPdf']);

        Route::apiResource('restaurant-tables', RestaurantTableController::class);

        Route::post('cash-drawer/open', [CashDrawerController::class, 'open']);
        Route::post('cash-drawer/close', [CashDrawerController::class, 'close']);
        Route::get('cash-drawer/current', [CashDrawerController::class, 'current']);
        Route::get('cash-drawer-sessions', [CashDrawerController::class, 'index']);
        Route::post('cash-drawer-sessions/{session}/recoveries', [CashDrawerController::class, 'recordRecovery']);

        Route::get('sales/held', [SaleController::class, 'held']);
        Route::apiResource('sales', SaleController::class);
        Route::post('sales/{sale}/hold', [SaleController::class, 'hold']);
        Route::post('sales/{sale}/complete', [SaleController::class, 'complete']);
        Route::post('sales/{sale}/void', [SaleController::class, 'void']);
        Route::post('sales/{sale}/refund', [SaleController::class, 'refund']);
        Route::post('sales/{sale}/payments', [SaleController::class, 'addPayment']);
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt']);

        Route::get('pos-reports/daily-summary', [PosReportController::class, 'dailySummary'])
            ->middleware('permission:pos-reports.view');

        // Sales: Price Lists, Promotions, Quotations, Invoices, Customer Payments
        Route::apiResource('price-lists', PriceListController::class);
        Route::apiResource('price-lists.items', PriceListItemController::class);

        Route::apiResource('promotions', PromotionController::class);

        Route::apiResource('quotations', QuotationController::class);
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->middleware('permission:quotations.send');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->middleware('permission:quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->middleware('permission:quotations.reject');
        Route::post('quotations/{quotation}/convert-to-invoice', [QuotationController::class, 'convertToInvoice'])->middleware('permission:quotations.convert-to-invoice');
        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf']);
        Route::post('quotations/{quotation}/email', [QuotationController::class, 'email']);

        Route::get('invoices/overdue', [InvoiceController::class, 'overdue']);
        Route::apiResource('invoices', InvoiceController::class);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email']);
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('permission:invoices.send');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:invoices.cancel');

        Route::apiResource('customer-payments', CustomerPaymentController::class);

        // Accounting Reports & Dashboard
        Route::apiResource('accounting-periods', AccountingPeriodController::class);
        Route::post('accounting-periods/{accounting_period}/close', [AccountingPeriodController::class, 'close'])
            ->middleware('permission:accounting-periods.close');

        Route::middleware('permission:reports.view')->group(function () {
            Route::get('reports/trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('reports/profit-and-loss', [ReportController::class, 'profitAndLoss']);
            Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('reports/general-ledger', [ReportController::class, 'generalLedger']);
            Route::get('reports/cash-flow', [ReportController::class, 'cashFlow']);
            Route::get('reports/snapshots', [ReportController::class, 'snapshots']);
        });
        Route::post('reports/{type}/snapshot', [ReportController::class, 'snapshot'])
            ->middleware('permission:reports.snapshot');

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware('permission:dashboard.view');
    });
});
