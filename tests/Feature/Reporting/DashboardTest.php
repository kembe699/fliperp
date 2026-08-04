<?php

use App\Models\CashDrawerSession;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->from = now()->startOfMonth()->toDateString();
    $this->to = now()->toDateString();
});

it('counts products whose total stock on hand has fallen to or below their reorder level', function () {
    $lowStock = createProduct($this->company, ['sku' => 'LOW-STOCK', 'reorder_level' => 20]);
    $healthyStock = createProduct($this->company, ['sku' => 'HEALTHY-STOCK', 'reorder_level' => 20]);

    app(StockMovementService::class)->record([
        'product_id' => $lowStock->id, 'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase', 'quantity' => 10,
    ]);
    app(StockMovementService::class)->record([
        'product_id' => $healthyStock->id, 'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase', 'quantity' => 100,
    ]);

    $response = $this->getJson("/api/v1/dashboard/summary?from={$this->from}&to={$this->to}")->assertOk();

    expect($response->json('data.low_stock_product_count'))->toBe(1);
});

it('totals overdue invoices by outstanding balance', function () {
    $customer = createCustomer($this->company);
    $product = createProduct($this->company, ['selling_price' => 100]);

    $invoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => $customer->id,
        'due_date' => now()->subDays(5)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100]],
    ])->json('data.id');
    $this->postJson("/api/v1/invoices/{$invoiceId}/send")->assertOk();

    $response = $this->getJson("/api/v1/dashboard/summary?from={$this->from}&to={$this->to}")->assertOk();

    expect((float) $response->json('data.overdue_invoices_total'))->toBe(300.0);
});

it('totals overdue supplier bills by outstanding balance', function () {
    $supplier = createSupplier($this->company);
    $product = createProduct($this->company);

    $poId = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id, 'warehouse_id' => $this->warehouse->id, 'supplier_id' => $supplier->id,
        'reference_number' => 'DASH-PO-001', 'order_date' => now()->subDays(40)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 50]],
    ])->json('data.id');
    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();
    $this->postJson("/api/v1/purchase-orders/{$poId}/approve")->assertOk();

    $grnId = $this->postJson('/api/v1/goods-received-notes', [
        'purchase_order_id' => $poId, 'warehouse_id' => $this->warehouse->id, 'supplier_id' => $supplier->id,
        'reference_number' => 'DASH-GRN-001', 'received_date' => now()->subDays(35)->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity_received' => 10, 'unit_cost' => 50, 'condition' => 'good']],
    ])->json('data.id');
    $this->postJson("/api/v1/goods-received-notes/{$grnId}/confirm")->assertOk();
    // Supplier default payment_terms_days=30, so bill due_date is 5 days ago.

    $response = $this->getJson("/api/v1/dashboard/summary?from={$this->from}&to={$this->to}")->assertOk();

    expect((float) $response->json('data.overdue_supplier_bills_total'))->toBe(500.0);
});

it('counts pending leave requests for the company', function () {
    $employee = createEmployee($this->company, $this->branch);
    $leaveType = LeaveType::create(['company_id' => $this->company->id, 'name' => 'Annual', 'days_per_year' => 20]);

    LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(7)->toDateString(),
        'days_count' => 3, 'status' => 'pending',
    ]);
    LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString(),
        'days_count' => 2, 'status' => 'approved',
    ]);

    $response = $this->getJson("/api/v1/dashboard/summary?from={$this->from}&to={$this->to}")->assertOk();

    expect($response->json('data.pending_leave_requests_count'))->toBe(1);
});

it('sums today\'s cash drawer variance', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 1000])->assertCreated();
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 950])->assertOk();

    $session = CashDrawerSession::first();
    expect((float) $session->variance)->toBe(-50.0);

    $response = $this->getJson("/api/v1/dashboard/summary?from={$this->from}&to={$this->to}")->assertOk();

    expect((float) $response->json('data.cash_drawer_variance'))->toBe(-50.0);
});

it('totals completed sales and estimates gross profit within the date range', function () {
    $product = createProduct($this->company, ['cost_price' => 40, 'selling_price' => 100]);
    $cash = createPaymentType($this->company);

    app(StockMovementService::class)->record([
        'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase', 'quantity' => 20,
    ]);

    $sale = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100]],
    ])->json('data');

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", ['payment_type_id' => $cash->id, 'amount' => 500])->assertCreated();
    $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk();

    $response = $this->getJson("/api/v1/dashboard/summary?from={$this->from}&to={$this->to}")->assertOk();

    expect((float) $response->json('data.total_sales'))->toBe(500.0);
    expect((float) $response->json('data.gross_profit_estimate'))->toBe(300.0); // 500 revenue - 200 COGS
});
