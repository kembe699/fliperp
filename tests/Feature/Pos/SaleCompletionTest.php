<?php

use App\Models\JournalEntryLine;
use App\Models\SaleItem;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->taxRate = createTaxRate($this->company, ['rate' => 18]);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 100, 'selling_price' => 200]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 50,
    ]);

    $this->createHeldSale = function (float $quantity = 4) {
        return $this->postJson('/api/v1/sales', [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 200, 'tax_rate_id' => $this->taxRate->id],
            ],
        ])->json('data');
    };
});

it('rejects completing a sale whose payments do not sum to the total amount', function () {
    $sale = ($this->createHeldSale)();

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => 100,
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$sale['id']}/complete")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('records a negative sale stock movement and reduces quantity_on_hand on completion', function () {
    $sale = ($this->createHeldSale)(4);

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => $sale['total_amount'],
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'sale',
        'quantity' => -4,
    ]);

    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 46,
    ]);
});

it('posts a balanced journal entry with correct revenue, tax and COGS lines', function () {
    $sale = ($this->createHeldSale)(4);
    // subtotal = 4 * 200 = 800, tax = 800 * 18% = 144, total = 944
    // COGS = 4 * cost_price(100) = 400

    $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => 944,
    ])->assertCreated();

    $response = $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk();

    expect((float) $response->json('data.subtotal'))->toBe(800.0);
    expect((float) $response->json('data.tax_amount'))->toBe(144.0);
    expect((float) $response->json('data.total_amount'))->toBe(944.0);

    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();
    $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'posted']);

    $lines = JournalEntryLine::where('journal_entry_id', $journalEntryId)->get();
    expect((float) $lines->sum('debit'))->toBe((float) $lines->sum('credit'));

    $cashLine = $lines->firstWhere('account_id', $this->accounts['1000']->id);
    $revenueLine = $lines->firstWhere('account_id', $this->accounts['4000']->id);
    $taxLine = $lines->firstWhere('account_id', $this->accounts['2300']->id);
    $cogsLine = $lines->firstWhere('account_id', $this->accounts['5300']->id);
    $inventoryLine = $lines->firstWhere('account_id', $this->accounts['1200']->id);

    expect((float) $cashLine->debit)->toBe(944.0);
    expect((float) $revenueLine->credit)->toBe(800.0);
    expect((float) $taxLine->credit)->toBe(144.0);
    expect((float) $cogsLine->debit)->toBe(400.0);
    expect((float) $inventoryLine->credit)->toBe(400.0);
});

it('rejects completing a sale with no items', function () {
    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 200],
        ],
    ])->json('data.id');

    // Empty the cart by editing it down is not supported; instead simulate
    // via direct deletion of the sale_item to exercise the guard.
    SaleItem::where('sale_id', $saleId)->delete();

    $this->postJson("/api/v1/sales/{$saleId}/complete")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
