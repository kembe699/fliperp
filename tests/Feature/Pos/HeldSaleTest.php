<?php

use App\Models\StockMovement;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('creates a held sale with no stock movement and no journal entry', function () {
    $response = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100],
        ],
    ])->assertCreated();

    expect($response->json('data.status'))->toBe('held');
    expect($response->json('data.journal_entry_id'))->toBeNull();

    expect(StockMovement::where('product_id', $this->product->id)->where('movement_type', 'sale')->count())->toBe(0);
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->product->id, 'quantity_on_hand' => 100]);
});

it('lists held sales scoped to the current cash drawer session', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $heldId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->json('data.id');

    $response = $this->getJson('/api/v1/sales/held')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toContain($heldId);
});

it('excludes a completed sale from the held sales list', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $paymentType = createPaymentType($this->company);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->json('data.id');

    $this->postJson("/api/v1/sales/{$saleId}/payments", ['payment_type_id' => $paymentType->id, 'amount' => 100])
        ->assertCreated();
    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    $response = $this->getJson('/api/v1/sales/held')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->not->toContain($saleId);
});

it('only allows editing and deleting a sale while it is held', function () {
    $paymentType = createPaymentType($this->company);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->json('data.id');

    $this->postJson("/api/v1/sales/{$saleId}/payments", ['payment_type_id' => $paymentType->id, 'amount' => 100])
        ->assertCreated();
    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    $this->putJson("/api/v1/sales/{$saleId}", ['discount_amount' => 5])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->deleteJson("/api/v1/sales/{$saleId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
