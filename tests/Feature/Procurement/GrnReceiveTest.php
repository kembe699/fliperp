<?php

use App\Models\GoodsReceivedNote;
use App\Models\StockLevel;
use App\Models\SupplierBill;
use Laravel\Sanctum\Sanctum;

// Receiving goods is one step: stock in, ledger entry and the supplier bill.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->supplier = createSupplier($this->company);
    $this->product = createProduct($this->company, ['name' => 'Rice 25kg']);
    Sanctum::actingAs($this->admin, ['*']);

    $this->grn = fn (array $extra = [], float $cost = 200) => $this->postJson('/api/v1/goods-received-notes', $extra + [
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'GRN-'.fake()->unique()->numerify('####'),
        'received_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity_received' => 5, 'unit_cost' => $cost, 'condition' => 'good']],
    ]);
});

it('receives the goods and creates the supplier bill the moment the GRN is saved', function () {
    $res = ($this->grn)(['receive' => true])->assertCreated()->assertJsonPath('data.status', 'confirmed');

    $bill = SupplierBill::where('grn_id', $res->json('data.id'))->first();
    expect($bill)->not->toBeNull()
        ->and((float) $bill->total_amount)->toBe(1000.0)
        ->and($bill->status)->toBe('unpaid')
        ->and($bill->journal_entry_id)->not->toBeNull()
        ->and((float) StockLevel::where('product_id', $this->product->id)->sum('quantity_on_hand'))->toBe(5.0);
});

it('still saves a draft when asked, with no stock or bill until it is received', function () {
    $id = ($this->grn)()->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
    expect(SupplierBill::where('grn_id', $id)->exists())->toBeFalse();

    $this->postJson("/api/v1/goods-received-notes/{$id}/confirm")->assertOk();
    expect(SupplierBill::where('grn_id', $id)->exists())->toBeTrue();
});

it('refuses to receive a good item at a cost of 0, naming it, and saves nothing', function () {
    ($this->grn)(['receive' => true], 0)->assertStatus(422)->assertJsonFragment(['Enter the unit cost for Rice 25kg — the supplier bill is made from the cost of the goods received.']);

    expect(GoodsReceivedNote::count())->toBe(0);
});

it('shows a purchase order its receipts and bills', function () {
    $po = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id,
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'reference_number' => 'PO-LINK-1',
        'order_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity_ordered' => 5, 'unit_cost' => 200]],
    ])->assertCreated()->json('data');
    $this->postJson("/api/v1/purchase-orders/{$po['id']}/submit");
    $this->postJson("/api/v1/purchase-orders/{$po['id']}/approve");

    ($this->grn)(['receive' => true, 'purchase_order_id' => $po['id'], 'items' => [
        ['product_id' => $this->product->id, 'purchase_order_item_id' => $po['items'][0]['id'], 'quantity_received' => 5, 'unit_cost' => 200, 'condition' => 'good'],
    ]])->assertCreated();

    $show = $this->getJson("/api/v1/purchase-orders/{$po['id']}")->assertOk();
    expect($show->json('data.goods_received_notes'))->toHaveCount(1)
        ->and($show->json('data.supplier_bills'))->toHaveCount(1)
        ->and((float) $show->json('data.supplier_bills.0.total_amount'))->toBe(1000.0);
});
