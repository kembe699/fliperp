<?php

use App\Models\LeaveType;
use App\Models\SalaryStructure;
use App\Models\StatutoryDeductionRule;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
});

it('notifies a branch manager when a leave request is submitted, and the requester when it is decided', function () {
    $admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $employee = createEmployee($this->company, $this->branch);
    $employeeUser = createUserWithRole('employee', $this->company, $this->branch);
    $employee->update(['user_id' => $employeeUser->id]);

    $leaveType = LeaveType::create([
        'company_id' => $this->company->id,
        'name' => 'Annual Leave',
        'days_per_year' => 20,
        'is_paid' => true,
    ]);

    // The 'employee' role has zero Spatie permissions by design (see
    // RoleAndPermissionSeeder) — employees submit their own leave through
    // the self-service portal, which authorizes by linked employee_id
    // rather than a permission check.
    Sanctum::actingAs($employeeUser, ['*']);
    $leaveRequestId = $this->postJson('/api/v1/employee-portal/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-03',
    ])->assertCreated()->json('data.id');

    // company_admin has leave-requests.approve — should have been notified of the submission.
    expect($admin->notifications()->where('type', \App\Notifications\LeaveRequestSubmitted::class)->count())->toBe(1);
    // The employee's own account shouldn't be notified about its own submission.
    expect($employeeUser->notifications()->count())->toBe(0);

    Sanctum::actingAs($admin, ['*']);
    $this->postJson("/api/v1/leave-requests/{$leaveRequestId}/approve")->assertOk();

    expect($employeeUser->notifications()->where('type', \App\Notifications\LeaveRequestDecided::class)->count())->toBe(1);
    $notification = $employeeUser->notifications()->first();
    expect($notification->data['category'])->toBe('leave_request_decided');
    expect($notification->data['body'])->toContain('approved');
});

it('notifies procurement approvers (but not the submitter) when a purchase order is submitted', function () {
    $admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $procurementOfficer = createUserWithRole('procurement_officer', $this->company, $this->branch);
    $warehouse = createWarehouse($this->company, $this->branch);
    $supplier = createSupplier($this->company);
    $product = createProduct($this->company);

    Sanctum::actingAs($admin, ['*']);

    $poId = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id,
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'reference_number' => 'PO-TEST-1',
        'order_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost' => 20],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();

    expect($procurementOfficer->notifications()->where('type', \App\Notifications\PurchaseOrderApprovalNeeded::class)->count())->toBe(1);
    // company_admin also has purchase-orders.approve, but shouldn't be told about its own submission.
    expect($admin->notifications()->count())->toBe(0);
});

it('alerts inventory managers exactly once when a stock movement crosses below reorder_level', function () {
    $branchManager = createUserWithRole('branch_manager', $this->company, $this->branch);
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $warehouse = createWarehouse($this->company, $this->branch);
    $product = createProduct($this->company, ['reorder_level' => 10]);

    Sanctum::actingAs($cashier, ['*']);
    $stockMovementService = app(StockMovementService::class);

    // Starts comfortably above reorder_level.
    $stockMovementService->record([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 20,
    ]);
    expect($branchManager->notifications()->count())->toBe(0);

    // Crosses below reorder_level (20 -> 5): should alert exactly once.
    $stockMovementService->record([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -15,
    ]);
    expect($branchManager->notifications()->where('type', \App\Notifications\LowStockAlert::class)->count())->toBe(1);

    // Stays low but doesn't cross again — no duplicate alert.
    $stockMovementService->record([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -1,
    ]);
    expect($branchManager->notifications()->where('type', \App\Notifications\LowStockAlert::class)->count())->toBe(1);

    // Cashiers aren't inventory managers for this purpose.
    expect($cashier->notifications()->count())->toBe(0);
});

it('notifies company_admin (but not the processor) when a payroll run is processed', function () {
    $admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $employee = createEmployee($this->company, $this->branch);

    SalaryStructure::create([
        'employee_id' => $employee->id,
        'basic_salary' => 500000,
        'allowances' => [],
        'effective_date' => now()->subMonth()->toDateString(),
    ]);

    StatutoryDeductionRule::create([
        'company_id' => $this->company->id,
        'name' => 'Flat Tax',
        'type' => 'tax',
        'calculation_type' => 'percentage',
        'config' => ['rate' => 0.1],
        'country_code' => 'UGA',
        'is_active' => true,
    ]);

    Sanctum::actingAs($admin, ['*']);
    $runId = $this->postJson('/api/v1/payroll-runs', [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ])->json('data.id');

    $this->postJson("/api/v1/payroll-runs/{$runId}/process")->assertOk();

    // The only payroll-runs.process holder in this company is the admin who
    // just did it themselves — so nobody else to notify, and they aren't
    // notified about their own action.
    expect($admin->notifications()->where('type', \App\Notifications\PayrollRunProcessed::class)->count())->toBe(0);

    $secondAdmin = createUserWithRole('company_admin', $this->company, $this->branch);
    // Sanity: confirm the notification mechanism itself works by processing
    // a second run with two admins present.
    $runId2 = $this->postJson('/api/v1/payroll-runs', [
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
    ])->json('data.id');
    $this->postJson("/api/v1/payroll-runs/{$runId2}/process")->assertOk();

    expect($secondAdmin->notifications()->where('type', \App\Notifications\PayrollRunProcessed::class)->count())->toBe(1);
});

it('notifies branch managers (but not the cashier who voided it) when a sale is voided', function () {
    $branchManager = createUserWithRole('branch_manager', $this->company, $this->branch);
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $warehouse = createWarehouse($this->company, $this->branch);
    $paymentType = createPaymentType($this->company);
    $product = createProduct($this->company, ['cost_price' => 10, 'selling_price' => 20]);

    Sanctum::actingAs($cashier, ['*']);
    app(StockMovementService::class)->record([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 50,
    ]);

    $sale = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 20]],
    ])->json('data');

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
        'payment_type_id' => $paymentType->id,
        'amount' => $sale['total_amount'],
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk();
    $this->postJson("/api/v1/sales/{$sale['id']}/void")->assertOk();

    expect($branchManager->notifications()->where('type', \App\Notifications\SaleVoided::class)->count())->toBe(1);
    expect($cashier->notifications()->count())->toBe(0);
});

it('notifies branch managers (but not the cashier who closed it) when a cash drawer closes with a variance', function () {
    $branchManager = createUserWithRole('branch_manager', $this->company, $this->branch);
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);

    Sanctum::actingAs($cashier, ['*']);
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])->assertCreated();
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 48000])->assertOk();

    expect($branchManager->notifications()->where('type', \App\Notifications\CashDrawerVarianceFlagged::class)->count())->toBe(1);
    expect($cashier->notifications()->count())->toBe(0);
});

it('never leaks a notification to a user in a different company', function () {
    $branchManager = createUserWithRole('branch_manager', $this->company, $this->branch);
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);

    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherBranchManager = createUserWithRole('branch_manager', $otherCompany, $otherBranch);

    Sanctum::actingAs($cashier, ['*']);
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])->assertCreated();
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 48000])->assertOk();

    expect($branchManager->notifications()->count())->toBe(1);
    expect($otherBranchManager->notifications()->count())->toBe(0);
});

it('lists notifications filtered by read/unread, reports the unread count, and marks read', function () {
    $branchManager = createUserWithRole('branch_manager', $this->company, $this->branch);
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);

    Sanctum::actingAs($cashier, ['*']);
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])->assertCreated();
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 45000])->assertOk();

    Sanctum::actingAs($branchManager, ['*']);

    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 1);

    $unread = $this->getJson('/api/v1/notifications?status=unread')->assertOk();
    expect($unread->json('data'))->toHaveCount(1);
    $notificationId = $unread->json('data.0.id');

    $this->postJson("/api/v1/notifications/{$notificationId}/read")->assertOk();

    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);
    $this->getJson('/api/v1/notifications?status=read')->assertOk()->assertJsonCount(1, 'data');

    // mark-all-as-read on an already-all-read inbox is a harmless no-op.
    $this->postJson('/api/v1/notifications/read-all')->assertOk();
    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);
});
