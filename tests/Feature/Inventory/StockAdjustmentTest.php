<?php

use App\Models\StockAdjustment;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->productShort = createProduct($this->company, ['sku' => 'ADJ-SHORT']);
    $this->productExact = createProduct($this->company, ['sku' => 'ADJ-EXACT']);

    Sanctum::actingAs($this->admin, ['*']);

    $service = app(StockMovementService::class);
    $service->record(['product_id' => $this->productShort->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 50]);
    $service->record(['product_id' => $this->productExact->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 20]);
});

it('calculates variance correctly and does not move stock until approved', function () {
    $response = $this->postJson('/api/v1/stock-adjustments', [
        'warehouse_id' => $this->warehouse->id,
        'reference_number' => 'ADJ-001',
        'reason' => 'Monthly stock count',
        'items' => [
            ['product_id' => $this->productShort->id, 'counted_quantity' => 45],
            ['product_id' => $this->productExact->id, 'counted_quantity' => 20],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'draft');
    $adjustmentId = $response->json('data.id');

    $items = collect($response->json('data.items'))->keyBy('product_id');
    expect((float) $items[$this->productShort->id]['system_quantity'])->toBe(50.0);
    expect((float) $items[$this->productShort->id]['counted_quantity'])->toBe(45.0);
    expect((float) $items[$this->productShort->id]['variance'])->toBe(-5.0);
    expect((float) $items[$this->productExact->id]['variance'])->toBe(0.0);

    // Draft: stock must be untouched.
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->productShort->id, 'quantity_on_hand' => 50]);
    $this->assertDatabaseMissing('stock_movements', ['reference_type' => StockAdjustment::class, 'reference_id' => $adjustmentId]);

    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->productShort->id, 'quantity_on_hand' => 45]);
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->productExact->id, 'quantity_on_hand' => 20]);

    // Only the item with a non-zero variance produces a movement.
    $this->assertDatabaseHas('stock_movements', [
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustmentId,
        'product_id' => $this->productShort->id,
        'quantity' => -5,
    ]);
    $this->assertDatabaseMissing('stock_movements', [
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustmentId,
        'product_id' => $this->productExact->id,
    ]);
});

it('rejects approving an already approved adjustment', function () {
    $adjustmentId = $this->postJson('/api/v1/stock-adjustments', [
        'warehouse_id' => $this->warehouse->id,
        'reference_number' => 'ADJ-002',
        'items' => [
            ['product_id' => $this->productShort->id, 'counted_quantity' => 48],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")->assertOk();

    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('denies approval to roles without the stock-adjustments.approve permission', function () {
    $adjustmentId = $this->postJson('/api/v1/stock-adjustments', [
        'warehouse_id' => $this->warehouse->id,
        'reference_number' => 'ADJ-003',
        'items' => [
            ['product_id' => $this->productShort->id, 'counted_quantity' => 40],
        ],
    ])->json('data.id');

    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($cashier, ['*']);

    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")->assertStatus(403);
});
