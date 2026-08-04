<?php

use App\Models\AuditLog;
use App\Services\Inventory\StockMovementService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('rejects a movement that would push quantity_on_hand below zero by default', function () {
    $product = createProduct($this->company);
    $service = app(StockMovementService::class);

    $service->record(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 10]);

    expect(fn () => $service->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -15,
    ]))->toThrow(ValidationException::class);

    $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 10]);
});

it('allows negative stock when the product does not track inventory', function () {
    $product = createProduct($this->company, ['track_inventory' => false]);
    $service = app(StockMovementService::class);

    $movement = $service->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -5,
    ]);

    expect($movement->exists)->toBeTrue();
    $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => -5]);
});

it('allows negative stock when the allow_negative_stock override is passed', function () {
    $product = createProduct($this->company);
    $service = app(StockMovementService::class);

    $service->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -3,
        'allow_negative_stock' => true,
    ]);

    $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => -3]);
});

it('records an audit log entry with before/after quantities for a manual stock movement', function () {
    $product = createProduct($this->company);
    app(StockMovementService::class)->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 50,
    ]);

    $response = $this->postJson('/api/v1/stock-movements/manual', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => -8,
        'reason' => 'Damaged stock write-off',
    ]);

    $response->assertCreated()->assertJsonPath('data.quantity', -8);
    $movementId = $response->json('data.id');

    $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 42]);

    $this->assertDatabaseHas('audit_logs', [
        'module' => 'StockMovement',
        'action' => 'manual_stock_adjustment',
        'record_id' => $movementId,
    ]);

    $auditLog = AuditLog::where('record_id', $movementId)->where('module', 'StockMovement')->first();
    expect((float) $auditLog->old_values['quantity_on_hand'])->toBe(50.0);
    expect((float) $auditLog->new_values['quantity_on_hand'])->toBe(42.0);
});

it('denies the manual stock movement endpoint to roles without the permission', function () {
    $product = createProduct($this->company);
    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($cashier, ['*']);

    $this->postJson('/api/v1/stock-movements/manual', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
        'reason' => 'Should be denied',
    ])->assertStatus(403);
});
