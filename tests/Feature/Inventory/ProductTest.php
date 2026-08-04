<?php

use App\Models\StockMovement;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('performs CRUD on products', function () {
    $category = createCategory($this->company);
    $unit = createUnitOfMeasure($this->company);

    $response = $this->postJson('/api/v1/products', [
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'name' => 'Bottled Water',
        'sku' => 'WATER-500',
        'cost_price' => 500,
        'selling_price' => 1000,
        'reorder_level' => 20,
    ]);

    $response->assertCreated()->assertJsonPath('data.sku', 'WATER-500');
    $productId = $response->json('data.id');

    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/products/{$productId}")->assertOk();

    $this->putJson("/api/v1/products/{$productId}", ['selling_price' => 1200])
        ->assertOk()
        ->assertJsonPath('data.selling_price', 1200);

    $this->deleteJson("/api/v1/products/{$productId}")->assertOk()->assertJsonPath('success', true);
    $this->assertSoftDeleted('products', ['id' => $productId]);
});

it('manages variants nested under a product', function () {
    $product = createProduct($this->company);

    $response = $this->postJson("/api/v1/products/{$product->id}/variants", [
        'name' => 'Large / Red',
        'sku' => $product->sku.'-LG-RED',
        'price_adjustment' => 5,
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Large / Red');
    $variantId = $response->json('data.id');

    $this->getJson("/api/v1/products/{$product->id}/variants")->assertOk()->assertJsonCount(1, 'data');

    $this->putJson("/api/v1/products/{$product->id}/variants/{$variantId}", ['price_adjustment' => 7.5])
        ->assertOk()
        ->assertJsonPath('data.price_adjustment', 7.5);

    $this->deleteJson("/api/v1/products/{$product->id}/variants/{$variantId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('product_variants', ['id' => $variantId]);
});

it('prevents deleting a product that has stock movements', function () {
    $product = createProduct($this->company);

    StockMovement::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 10,
        'performed_by' => $this->admin->id,
        'moved_at' => now(),
    ]);

    $this->deleteJson("/api/v1/products/{$product->id}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});
