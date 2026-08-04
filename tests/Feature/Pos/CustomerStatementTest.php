<?php

use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);
    $this->customer = createCustomer($this->company, ['customer_type' => 'credit', 'credit_limit' => 10000]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('computes a running balance across a credit sale and a partial payment', function () {
    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100]],
    ])->json('data.id');
    // total = 500, no payment at completion (credit customer allows it)

    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    // Pay down 200 of the balance after completion.
    $this->postJson("/api/v1/sales/{$saleId}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => 200,
    ])->assertCreated();

    $response = $this->getJson("/api/v1/customers/{$this->customer->id}/statement")->assertOk();

    expect((float) $response->json('data.closing_balance'))->toBe(300.0);

    $transactions = $response->json('data.transactions');
    expect($transactions)->toHaveCount(2);
    expect((float) $transactions[0]['debit'])->toBe(500.0);
    expect((float) $transactions[0]['running_balance'])->toBe(500.0);
    expect((float) $transactions[1]['credit'])->toBe(200.0);
    expect((float) $transactions[1]['running_balance'])->toBe(300.0);
});
