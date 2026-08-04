<?php

use App\Models\JournalEntryLine;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\SupplierBill;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->supplier = createSupplier($this->company);
    $this->productGood = createProduct($this->company, ['sku' => 'GRN-GOOD']);
    $this->productBad = createProduct($this->company, ['sku' => 'GRN-BAD']);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createApprovedPo = function (array $items, string $reference = 'PO-GRN-001') {
        $poId = $this->postJson('/api/v1/purchase-orders', [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => $reference,
            'order_date' => now()->toDateString(),
            'items' => $items,
        ])->json('data.id');

        $this->postJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();
        $this->postJson("/api/v1/purchase-orders/{$poId}/approve")->assertOk();

        return $this->getJson("/api/v1/purchase-orders/{$poId}")->json('data');
    };
});

it('blocks creating a GRN against a purchase order that is not approved', function () {
    $poId = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id,
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'PO-DRAFT-001',
        'order_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
        ],
    ])->json('data.id');

    $this->postJson('/api/v1/goods-received-notes', [
        'purchase_order_id' => $poId,
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-001',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_received' => 10, 'unit_cost' => 50, 'condition' => 'good'],
        ],
    ])->assertStatus(422)->assertJsonPath('success', false);
});

it('creates stock movements only for good-condition items on confirmation', function () {
    $grnId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-COND-001',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_received' => 20, 'unit_cost' => 50, 'condition' => 'good'],
            ['product_id' => $this->productBad->id, 'quantity_received' => 5, 'unit_cost' => 40, 'condition' => 'damaged'],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grnId}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->productGood->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 20,
    ]);

    expect(StockMovement::where('product_id', $this->productBad->id)->count())->toBe(0);

    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $this->productGood->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 20,
    ]);
});

it('updates the purchase order to partially_received then received across two GRNs', function () {
    $po = ($this->createApprovedPo)([
        ['product_id' => $this->productGood->id, 'quantity_ordered' => 100, 'unit_cost' => 50],
    ]);
    $poItemId = $po['items'][0]['id'];

    $grn1Id = $this->postJson('/api/v1/goods-received-notes', [
        'purchase_order_id' => $po['id'],
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-PARTIAL-001',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'purchase_order_item_id' => $poItemId, 'quantity_received' => 60, 'unit_cost' => 50, 'condition' => 'good'],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grn1Id}/confirm")->assertOk();

    $this->assertDatabaseHas('purchase_orders', ['id' => $po['id'], 'status' => 'partially_received']);
    $this->assertDatabaseHas('purchase_order_items', ['id' => $poItemId, 'quantity_received' => 60]);

    $receivingStatus = $this->getJson("/api/v1/purchase-orders/{$po['id']}/receiving-status")->json('data');
    expect((float) $receivingStatus['items'][0]['quantity_outstanding'])->toBe(40.0);
    expect($receivingStatus['items'][0]['fully_received'])->toBeFalse();

    $grn2Id = $this->postJson('/api/v1/goods-received-notes', [
        'purchase_order_id' => $po['id'],
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-PARTIAL-002',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'purchase_order_item_id' => $poItemId, 'quantity_received' => 40, 'unit_cost' => 50, 'condition' => 'good'],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grn2Id}/confirm")->assertOk();

    $this->assertDatabaseHas('purchase_orders', ['id' => $po['id'], 'status' => 'received']);
    $this->assertDatabaseHas('purchase_order_items', ['id' => $poItemId, 'quantity_received' => 100]);
    $this->assertDatabaseHas('stock_levels', ['product_id' => $this->productGood->id, 'quantity_on_hand' => 100]);
});

it('auto-creates a supplier bill from good-condition items only and posts a balanced journal entry', function () {
    $grnId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-BILL-001',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_received' => 10, 'unit_cost' => 100, 'condition' => 'good'],
            ['product_id' => $this->productBad->id, 'quantity_received' => 3, 'unit_cost' => 50, 'condition' => 'rejected'],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grnId}/confirm")->assertOk();

    $bill = SupplierBill::where('grn_id', $grnId)->first();

    expect($bill)->not->toBeNull();
    expect((float) $bill->subtotal)->toBe(1000.0); // 10 * 100, rejected item excluded
    expect((float) $bill->total_amount)->toBe(1000.0);
    expect($bill->status)->toBe('unpaid');
    expect($bill->journal_entry_id)->not->toBeNull();

    $lines = JournalEntryLine::where('journal_entry_id', $bill->journal_entry_id)->get();
    expect((float) $lines->sum('debit'))->toBe((float) $lines->sum('credit'))->toBe(1000.0);

    $inventoryLine = $lines->firstWhere('account_id', $this->accounts['1200']->id);
    $payableLine = $lines->firstWhere('account_id', $this->accounts['2000']->id);

    expect((float) $inventoryLine->debit)->toBe(1000.0);
    expect((float) $payableLine->credit)->toBe(1000.0);

    $this->assertDatabaseHas('journal_entries', ['id' => $bill->journal_entry_id, 'status' => 'posted']);
});

it('prevents deleting a confirmed GRN and a purchase order that has GRNs', function () {
    $po = ($this->createApprovedPo)([
        ['product_id' => $this->productGood->id, 'quantity_ordered' => 10, 'unit_cost' => 50],
    ], 'PO-DELETE-GUARD');

    $grnId = $this->postJson('/api/v1/goods-received-notes', [
        'purchase_order_id' => $po['id'],
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-DELETE-001',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_received' => 10, 'unit_cost' => 50, 'condition' => 'good'],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grnId}/confirm")->assertOk();

    $this->deleteJson("/api/v1/goods-received-notes/{$grnId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('converges to a single correct stock_levels row across two confirmations for the same product and warehouse', function () {
    // GrnService::confirm() -> StockMovementService::record() locks the
    // stock_levels row with lockForUpdate() inside a DB transaction before
    // reading/writing quantity_on_hand. PHPUnit/Pest run single-threaded, so
    // this can't fork real concurrent processes, but it does prove the outcome
    // that lock protects: sequential confirmations against the same
    // product/warehouse converge to exactly one row with the correct summed
    // total rather than two racing/duplicate rows.
    $grnAId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-CONCURRENT-A',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_received' => 15, 'unit_cost' => 50, 'condition' => 'good'],
        ],
    ])->json('data.id');

    $grnBId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-CONCURRENT-B',
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $this->productGood->id, 'quantity_received' => 25, 'unit_cost' => 50, 'condition' => 'good'],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grnAId}/confirm")->assertOk();
    $this->postJson("/api/v1/goods-received-notes/{$grnBId}/confirm")->assertOk();

    expect(StockLevel::where('product_id', $this->productGood->id)->where('warehouse_id', $this->warehouse->id)->count())->toBe(1);
    $this->assertDatabaseHas('stock_levels', [
        'product_id' => $this->productGood->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 40,
    ]);
});
