<?php

use App\Models\PaymentType;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->taxRate = createTaxRate($this->company, ['rate' => 10]);
    $this->cash = createPaymentType($this->company, ['name' => 'Cash', 'type' => 'cash']);
    $this->card = createPaymentType($this->company, ['name' => 'Card', 'type' => 'card']);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);

    $this->completeSale = function (float $quantity, PaymentType $paymentType) {
        $sale = $this->postJson('/api/v1/sales', [
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 100, 'tax_rate_id' => $this->taxRate->id]],
        ])->json('data');

        $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
            'payment_type_id' => $paymentType->id,
            'amount' => $sale['total_amount'],
        ])->assertCreated();

        return $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk()->json('data');
    };
});

it('totals sales by payment type, tax collected and void count for the day', function () {
    ($this->completeSale)(2, $this->cash); // 200 + 20 tax = 220
    ($this->completeSale)(1, $this->card); // 100 + 10 tax = 110

    $voidedSale = ($this->completeSale)(1, $this->cash); // 100 + 10 tax = 110
    $this->postJson("/api/v1/sales/{$voidedSale['id']}/void")->assertOk();

    $response = $this->getJson('/api/v1/pos-reports/daily-summary?date='.now()->toDateString().'&branch_id='.$this->branch->id)
        ->assertOk();

    // Only the two completed (non-voided) sales count toward totals.
    expect((float) $response->json('data.total_sales'))->toBe(330.0);
    expect((float) $response->json('data.tax_collected'))->toBe(30.0);
    expect($response->json('data.void_count'))->toBe(1);

    $byPaymentType = collect($response->json('data.totals_by_payment_type'))->keyBy('payment_type_name');
    expect((float) $byPaymentType['Cash']['total'])->toBe(220.0);
    expect((float) $byPaymentType['Card']['total'])->toBe(110.0);
});
