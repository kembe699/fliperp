<?php

use App\Models\JournalEntry;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 50, 'selling_price' => 100]);
    $this->table = createRestaurantTable($this->company, $this->branch);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('reverses stock and the journal entry and frees the table when a completed dine-in sale is voided', function () {
    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'sale_type' => 'dine_in',
        'table_id' => $this->table->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 100],
        ],
    ])->json('data');

    $this->assertDatabaseHas('restaurant_tables', ['id' => $this->table->id, 'status' => 'occupied']);

    $this->postJson("/api/v1/sales/{$saleId['id']}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => $saleId['total_amount'],
    ])->assertCreated();

    $completed = $this->postJson("/api/v1/sales/{$saleId['id']}/complete")->assertOk()->json('data');

    $this->assertDatabaseHas('restaurant_tables', ['id' => $this->table->id, 'status' => 'available']);
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->product->id, 'quantity_on_hand' => 96]);

    $journalEntryId = $completed['journal_entry_id'];

    $voided = $this->postJson("/api/v1/sales/{$saleId['id']}/void")->assertOk()->json('data');

    expect($voided['status'])->toBe('voided');

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'return',
        'quantity' => 4,
    ]);

    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->product->id, 'quantity_on_hand' => 100]);

    $this->assertDatabaseHas('journal_entries', ['id' => $journalEntryId, 'status' => 'reversed']);
    $reversal = JournalEntry::where('reference_number', 'like', '%-REV')
        ->where('source_id', $saleId['id'])
        ->first();
    expect($reversal)->not->toBeNull();
    expect($reversal->status)->toBe('posted');

    $this->assertDatabaseHas('restaurant_tables', ['id' => $this->table->id, 'status' => 'available']);
});

it('rejects voiding a sale that is still held', function () {
    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/sales/{$saleId}/void")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
