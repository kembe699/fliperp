<?php

use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('allows completing a sale for a credit customer with only a partial payment', function () {
    $customer = createCustomer($this->company, ['customer_type' => 'credit', 'credit_limit' => 5000]);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/sales/{$saleId}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => 200,
    ])->assertCreated();

    $response = $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    expect($response->json('data.status'))->toBe('completed');
    expect((float) $response->json('data.amount_paid'))->toBe(200.0);
    expect((float) $response->json('data.total_amount'))->toBe(500.0);
    expect((float) $response->json('data.balance_due'))->toBe(300.0);

    $this->assertDatabaseHas('sales', ['id' => $saleId, 'amount_paid' => 200]);
});

it('rejects a partial payment for a non-credit customer', function () {
    $customer = createCustomer($this->company, ['customer_type' => 'regular']);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/sales/{$saleId}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => 50,
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$saleId}/complete")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects a credit sale that would exceed the customer credit limit', function () {
    $customer = createCustomer($this->company, ['customer_type' => 'credit', 'credit_limit' => 100]);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100],
        ],
    ])->json('data.id');
    // total_amount = 500, no payment made, outstanding 500 > credit_limit 100

    $this->postJson("/api/v1/sales/{$saleId}/complete")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('accumulates outstanding balance across multiple credit sales against the credit limit', function () {
    $customer = createCustomer($this->company, ['customer_type' => 'credit', 'credit_limit' => 700]);

    $firstSaleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $customer->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100]],
    ])->json('data.id');
    // total 500, fully outstanding, within 700 limit
    $this->postJson("/api/v1/sales/{$firstSaleId}/complete")->assertOk();

    $secondSaleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $customer->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100]],
    ])->json('data.id');
    // total 300, existing outstanding 500 + 300 = 800 > 700 limit

    $this->postJson("/api/v1/sales/{$secondSaleId}/complete")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
