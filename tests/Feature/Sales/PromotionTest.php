<?php

use App\Services\Sales\PromotionService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->product = createProduct($this->company, ['selling_price' => 100]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->promotionService = app(PromotionService::class);
});

it('computes a percentage_discount proportional to quantity and selling_price', function () {
    $promotion = createPromotion($this->company, ['type' => 'percentage_discount', 'value' => 10, 'applies_to' => 'all_products']);

    $discount = $this->promotionService->applicableDiscount($this->product, null, now()->toDateString(), 3);

    expect($discount)->toBe(30.0); // 100 * 3 * 10%
});

it('computes a fixed_discount per unit', function () {
    createPromotion($this->company, ['type' => 'fixed_discount', 'value' => 15, 'applies_to' => 'all_products']);

    $discount = $this->promotionService->applicableDiscount($this->product, null, now()->toDateString(), 4);

    expect($discount)->toBe(60.0); // 15 * 4
});

it('computes buy_x_get_y as one free unit per (value+1) units purchased', function () {
    createPromotion($this->company, ['type' => 'buy_x_get_y', 'value' => 3, 'applies_to' => 'all_products']);

    // buy 3 get 1: every 4 units, 1 free. 8 units -> 2 free units.
    $discount = $this->promotionService->applicableDiscount($this->product, null, now()->toDateString(), 8);

    expect($discount)->toBe(200.0); // 2 * 100
});

it('applies a category-scoped promotion only to products in that category', function () {
    $category = createCategory($this->company);
    $matchingProduct = createProduct($this->company, ['category' => $category, 'selling_price' => 50]);
    $otherProduct = createProduct($this->company, ['selling_price' => 50]);

    createPromotion($this->company, ['type' => 'percentage_discount', 'value' => 20, 'applies_to' => 'category', 'category_id' => $category->id]);

    expect($this->promotionService->applicableDiscount($matchingProduct, null, now()->toDateString(), 1))->toBe(10.0);
    expect($this->promotionService->applicableDiscount($otherProduct, null, now()->toDateString(), 1))->toBe(0.0);
});

it('applies a specific_products promotion only to the listed products', function () {
    $includedProduct = createProduct($this->company, ['selling_price' => 40]);
    $excludedProduct = createProduct($this->company, ['selling_price' => 40]);

    $promotion = createPromotion($this->company, ['type' => 'fixed_discount', 'value' => 5, 'applies_to' => 'specific_products']);
    $promotion->products()->sync([$includedProduct->id]);

    expect($this->promotionService->applicableDiscount($includedProduct, null, now()->toDateString(), 1))->toBe(5.0);
    expect($this->promotionService->applicableDiscount($excludedProduct, null, now()->toDateString(), 1))->toBe(0.0);
});

it('ignores a promotion outside its date range', function () {
    createPromotion($this->company, [
        'type' => 'percentage_discount', 'value' => 50, 'applies_to' => 'all_products',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
    ]);

    expect($this->promotionService->applicableDiscount($this->product, null, now()->toDateString(), 1))->toBe(0.0);
});

it('ignores an inactive promotion', function () {
    createPromotion($this->company, ['type' => 'percentage_discount', 'value' => 50, 'applies_to' => 'all_products', 'is_active' => false]);

    expect($this->promotionService->applicableDiscount($this->product, null, now()->toDateString(), 1))->toBe(0.0);
});

it('lets a line item override the promotion-suggested discount_amount', function () {
    createPromotion($this->company, ['type' => 'percentage_discount', 'value' => 10, 'applies_to' => 'all_products']);
    $customer = createCustomer($this->company);

    $response = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'discount_amount' => 25],
        ],
    ])->assertCreated();

    // Explicit discount_amount (25) wins over the promo suggestion (10).
    expect((float) $response->json('data.items.0.discount_amount'))->toBe(25.0);
});

it('auto-suggests the promotion discount when discount_amount is omitted', function () {
    createPromotion($this->company, ['type' => 'percentage_discount', 'value' => 10, 'applies_to' => 'all_products']);
    $customer = createCustomer($this->company);

    $response = $this->postJson('/api/v1/quotations', [
        'branch_id' => $this->branch->id,
        'customer_id' => $customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect((float) $response->json('data.items.0.discount_amount'))->toBe(10.0);
});
