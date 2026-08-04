<?php

use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch(['currency_code' => 'UGX']);
    seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 100, 'selling_price' => 200]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 50,
    ]);

    $sale = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 200],
        ],
    ])->json('data');

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => $sale['total_amount'],
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk();

    $this->saleId = $sale['id'];
});

it('returns receipt-safe sale details from the public verify endpoint without authentication', function () {
    // A fresh, un-authenticated test case — no Sanctum::actingAs — mirrors a
    // customer scanning the QR code with no session of their own.
    $this->app['auth']->forgetGuards();

    $response = $this->getJson("/api/v1/public/sales/{$this->saleId}/verify");

    $response->assertOk()
        ->assertJsonPath('data.reference_number', fn ($value) => str_starts_with($value, 'SALE-'))
        ->assertJsonPath('data.total_amount', 400)
        ->assertJsonPath('data.currency_code', 'UGX')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.company_name', $this->company->name);
});

it('returns 404 for a sale id that does not exist', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/public/sales/999999/verify')->assertStatus(404);
});
