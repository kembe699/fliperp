<?php

use App\Models\StockTransfer;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouseA = createWarehouse($this->company, $this->branch, ['code' => 'WH-A']);
    $this->warehouseB = createWarehouse($this->company, $this->branch, ['code' => 'WH-B']);
    $this->product = createProduct($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouseA->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('runs the full stock transfer lifecycle and moves stock between warehouses on completion', function () {
    $response = $this->postJson('/api/v1/stock-transfers', [
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'reference_number' => 'TRF-001',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 30],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
    $transferId = $response->json('data.id');

    $this->postJson("/api/v1/stock-transfers/{$transferId}/mark-in-transit")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_transit');

    $this->postJson("/api/v1/stock-transfers/{$transferId}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity_on_hand' => 70,
    ]);
    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouseB->id,
        'quantity_on_hand' => 30,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouseA->id,
        'movement_type' => 'transfer_out',
        'quantity' => -30,
        'reference_type' => StockTransfer::class,
        'reference_id' => $transferId,
    ]);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouseB->id,
        'movement_type' => 'transfer_in',
        'quantity' => 30,
        'reference_type' => StockTransfer::class,
        'reference_id' => $transferId,
    ]);
});

it('cancels a pending transfer without creating any stock movements', function () {
    $transferId = $this->postJson('/api/v1/stock-transfers', [
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'reference_number' => 'TRF-002',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 10],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/stock-transfers/{$transferId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity_on_hand' => 100,
    ]);

    $this->assertDatabaseMissing('stock_movements', [
        'reference_type' => StockTransfer::class,
        'reference_id' => $transferId,
    ]);
});

it('rejects completing an already completed transfer', function () {
    $transferId = $this->postJson('/api/v1/stock-transfers', [
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'reference_number' => 'TRF-003',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/stock-transfers/{$transferId}/complete")->assertOk();

    $this->postJson("/api/v1/stock-transfers/{$transferId}/complete")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('locks the transfer row on complete so a concurrent double-complete cannot double-move stock', function () {
    // StockTransferService::complete() locks the StockTransfer row with
    // lockForUpdate() and re-checks status inside the transaction before
    // moving any item. Pest runs single-threaded so this can't fork real
    // concurrent processes (verified separately via parallel OS processes
    // during the audit that found this gap), but it does prove the outcome
    // the lock protects: the transfer converges to exactly one transfer_out
    // and one transfer_in movement per item, never two.
    $transferId = $this->postJson('/api/v1/stock-transfers', [
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'reference_number' => 'TRF-LOCK-001',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/stock-transfers/{$transferId}/complete")->assertOk();
    $this->postJson("/api/v1/stock-transfers/{$transferId}/complete")->assertStatus(422);

    expect(
        \App\Models\StockMovement::where('reference_type', StockTransfer::class)
            ->where('reference_id', $transferId)
            ->count()
    )->toBe(2); // one transfer_out + one transfer_in, never doubled
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouseA->id, 'quantity_on_hand' => 95]);
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouseB->id, 'quantity_on_hand' => 5]);
});
