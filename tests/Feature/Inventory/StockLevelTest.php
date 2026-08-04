<?php

use App\Models\StockLevel;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('correctly recalculates quantity_on_hand after purchase, sale and adjustment movements', function () {
    $product = createProduct($this->company, ['reorder_level' => 5]);
    $service = app(StockMovementService::class);

    $service->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);

    $level = StockLevel::where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $level->quantity_on_hand)->toBe(100.0);

    $service->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -30,
    ]);

    expect((float) $level->fresh()->quantity_on_hand)->toBe(70.0);

    $service->record([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'adjustment',
        'quantity' => -5,
    ]);

    expect((float) $level->fresh()->quantity_on_hand)->toBe(65.0);
});

it('filters stock levels by warehouse and product', function () {
    $productA = createProduct($this->company);
    $productB = createProduct($this->company);
    $service = app(StockMovementService::class);

    $service->record(['product_id' => $productA->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 10]);
    $service->record(['product_id' => $productB->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 20]);

    $response = $this->getJson('/api/v1/stock-levels?product_id='.$productA->id);

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.product_id'))->toBe($productA->id);
});

it('returns only products at or below their reorder_level when low_stock filter is set', function () {
    $lowStockProduct = createProduct($this->company, ['reorder_level' => 50]);
    $healthyProduct = createProduct($this->company, ['reorder_level' => 10]);
    $service = app(StockMovementService::class);

    $service->record(['product_id' => $lowStockProduct->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 20]);
    $service->record(['product_id' => $healthyProduct->id, 'warehouse_id' => $this->warehouse->id, 'movement_type' => 'purchase', 'quantity' => 100]);

    $response = $this->getJson('/api/v1/stock-levels?low_stock=1');

    $response->assertOk();
    $productIds = collect($response->json('data'))->pluck('product_id');

    expect($productIds)->toContain($lowStockProduct->id);
    expect($productIds)->not->toContain($healthyProduct->id);
});
