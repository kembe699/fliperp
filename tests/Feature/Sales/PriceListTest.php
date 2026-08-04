<?php

use App\Models\PriceList;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('unsets the previous default price list when a new one is marked default', function () {
    $first = $this->postJson('/api/v1/price-lists', [
        'name' => 'First List', 'currency_code' => 'USD', 'is_default' => true,
    ])->json('data.id');

    $this->postJson('/api/v1/price-lists', [
        'name' => 'Second List', 'currency_code' => 'USD', 'is_default' => true,
    ])->assertCreated()->assertJsonPath('data.is_default', true);

    $this->assertDatabaseHas('price_lists', ['id' => $first, 'is_default' => false]);
    expect(PriceList::where('is_default', true)->count())->toBe(1);
});

it('also unsets the default when updating an existing price list to become default', function () {
    $first = $this->postJson('/api/v1/price-lists', [
        'name' => 'First List', 'currency_code' => 'USD', 'is_default' => true,
    ])->json('data.id');

    $second = $this->postJson('/api/v1/price-lists', [
        'name' => 'Second List', 'currency_code' => 'USD', 'is_default' => false,
    ])->json('data.id');

    $this->putJson("/api/v1/price-lists/{$second}", ['is_default' => true])->assertOk();

    $this->assertDatabaseHas('price_lists', ['id' => $first, 'is_default' => false]);
    $this->assertDatabaseHas('price_lists', ['id' => $second, 'is_default' => true]);
});

it('applies the price list item price to a quotation line when no unit_price is given', function () {
    $priceListId = $this->postJson('/api/v1/price-lists', [
        'name' => 'Wholesale', 'currency_code' => 'USD',
    ])->json('data.id');

    $this->postJson("/api/v1/price-lists/{$priceListId}/items", [
        'product_id' => $this->product->id,
        'price' => 80,
    ])->assertCreated();

    $response = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'price_list_id' => $priceListId,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    expect((float) $response->json('data.items.0.unit_price'))->toBe(80.0);
    expect((float) $response->json('data.subtotal'))->toBe(160.0);
});

it('falls back to the product selling_price when no price list item matches', function () {
    $response = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect((float) $response->json('data.items.0.unit_price'))->toBe(100.0);
});
