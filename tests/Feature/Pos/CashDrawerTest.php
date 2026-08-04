<?php

use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->taxRate = createTaxRate($this->company);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 10, 'selling_price' => 20]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('rejects opening a second session while one is already open for the same user and branch', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 20000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('computes expected_closing and variance correctly from cash sale payments on close', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id],
        ],
    ])->json('data.id');

    $total = $this->getJson("/api/v1/sales/{$saleId}")->json('data.total_amount');

    $this->postJson("/api/v1/sales/{$saleId}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => $total,
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    // Drop 500 short of the expected drawer total to produce a negative variance.
    $closingFloat = 50000 + (float) $total - 500;

    $response = $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => $closingFloat])->assertOk();

    expect((float) $response->json('data.expected_closing'))->toBe(round(50000 + (float) $total, 2));
    expect((float) $response->json('data.closing_float'))->toBe(round($closingFloat, 2));
    expect((float) $response->json('data.variance'))->toBe(-500.0);
    expect($response->json('data.status'))->toBe('closed');
});

it('shows a live expected_closing on the open session before it is closed', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $this->getJson('/api/v1/cash-drawer/current')
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.expected_closing', 50000);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id]],
    ])->json('data.id');
    $total = $this->getJson("/api/v1/sales/{$saleId}")->json('data.total_amount');
    $this->postJson("/api/v1/sales/{$saleId}/payments", ['payment_type_id' => $this->paymentTypeCash->id, 'amount' => $total])->assertCreated();
    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    // The live figure on /current updates as cash sales come in, before any close request.
    $this->getJson('/api/v1/cash-drawer/current')
        ->assertOk()
        ->assertJsonPath('data.expected_closing', round(50000 + (float) $total, 2));
});

it('rejects closing the same session twice', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    // No open session left for this user, so the second close attempt fails
    // the same way any close-with-nothing-open request would.
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('filters cash drawer sessions by status', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])->assertCreated();
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 20000])->assertCreated();

    $this->getJson('/api/v1/cash-drawer-sessions?status=open')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.opening_float', 20000);

    $this->getJson('/api/v1/cash-drawer-sessions?status=closed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.opening_float', 10000);
});

it('filters sales by cash_drawer_session_id so a shift report can list exactly that shift\'s receipts', function () {
    $sessionId = $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->json('data.id');

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id]],
    ])->json('data.id');

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    // A sale rung up under a different (second) session must not show up
    // when filtering for the first session's receipts.
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 5000])->assertCreated();
    $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id]],
    ])->assertCreated();

    $this->getJson("/api/v1/sales?cash_drawer_session_id={$sessionId}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $saleId);
});

it('allows opening a new session again after the previous one is closed', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 15000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');
});
