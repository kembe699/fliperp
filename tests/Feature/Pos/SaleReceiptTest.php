<?php

use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
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

    $this->createCompletedSale = function () {
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

        return $sale['id'];
    };
});

it('returns an 80mm receipt PDF for a completed sale', function () {
    $saleId = ($this->createCompletedSale)();

    $response = $this->get("/api/v1/sales/{$saleId}/receipt");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('defaults to an inline disposition and switches to attachment with ?download=1', function () {
    $saleId = ($this->createCompletedSale)();

    $inline = $this->get("/api/v1/sales/{$saleId}/receipt");
    expect($inline->headers->get('Content-Disposition'))->toContain('inline');

    $attachment = $this->get("/api/v1/sales/{$saleId}/receipt?download=1");
    expect($attachment->headers->get('Content-Disposition'))->toContain('attachment');
});

it('returns 404 for a sale belonging to another company', function () {
    $saleId = ($this->createCompletedSale)();

    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherCashier = createUserWithRole('cashier', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherCashier, ['*']);

    $this->get("/api/v1/sales/{$saleId}/receipt")->assertStatus(404);
});

it('returns 403 for a user without sales.view permission', function () {
    $saleId = ($this->createCompletedSale)();

    $stockClerk = createUserWithRole('cashier', $this->company, $this->branch);
    $stockClerk->syncRoles([]);
    Sanctum::actingAs($stockClerk, ['*']);

    $this->get("/api/v1/sales/{$saleId}/receipt")->assertStatus(403);
});
