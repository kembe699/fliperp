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

it('allows opening a new session again after the previous one is closed', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 15000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');
});
