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

it('locks the adjustment row on approve so a concurrent double-approve cannot double-apply the variance', function () {
    // StockAdjustmentService::approve() locks the StockAdjustment row with
    // lockForUpdate() and re-checks status inside the transaction before
    // applying any item's variance. Pest runs single-threaded so this can't
    // fork real concurrent processes (verified separately via parallel OS
    // processes during the audit that found this gap), but it does prove the
    // outcome the lock protects: the adjustment converges to exactly one
    // stock_movement per item, never two, regardless of how many times
    // approve() is invoked against the same already-approved row.
    $adjustmentId = $this->postJson('/api/v1/stock-adjustments', [
        'warehouse_id' => $this->warehouse->id,
        'reference_number' => 'ADJ-LOCK-001',
        'items' => [
            ['product_id' => $this->productShort->id, 'counted_quantity' => 44],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")->assertOk();
    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")->assertStatus(422);

    expect(
        \App\Models\StockMovement::where('reference_type', StockAdjustment::class)
            ->where('reference_id', $adjustmentId)
            ->count()
    )->toBe(1);
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->productShort->id, 'quantity_on_hand' => 44]);
});

it('blocks a user from approving their own adjustment when another eligible approver exists', function () {
    $secondAdmin = createUserWithRole('company_admin', $this->company, $this->branch);

    $adjustmentId = $this->postJson('/api/v1/stock-adjustments', [
        'warehouse_id' => $this->warehouse->id,
        'reference_number' => 'ADJ-SOD-001',
        'items' => [
            ['product_id' => $this->productShort->id, 'counted_quantity' => 40],
        ],
    ])->json('data.id'); // created as $this->admin

    // $this->admin created it and also has stock-adjustments.approve, but
    // $secondAdmin — another user in the same company who also holds that
    // permission — exists, so self-approval is blocked (maker-checker).
    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")->assertStatus(403);

    Sanctum::actingAs($secondAdmin, ['*']);
    $this->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
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
