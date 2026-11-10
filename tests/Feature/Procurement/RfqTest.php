<?php

use App\Mail\DocumentMail;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

// Requests for quotation: ask suppliers, email it, record quotes, convert to a PO.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->rice = createProduct($this->company, ['name' => 'Rice 25kg', 'cost_price' => 40]);
    $this->oil = createProduct($this->company, ['name' => 'Cooking Oil 20L', 'cost_price' => 55]);
    $this->a = createSupplier($this->company, ['name' => 'Juba Traders', 'email' => 'sales@jubatraders.test']);
    $this->b = createSupplier($this->company, ['name' => 'Nile Wholesale', 'email' => 'quotes@nile.test']);
    $this->c = createSupplier($this->company, ['name' => 'No Email Co', 'email' => null]);

    $this->rfq = $this->postJson('/api/v1/rfqs', [
        'title' => 'Monthly dry goods',
        'warehouse_id' => $this->warehouse->id,
        'response_due_date' => now()->addWeek()->toDateString(),
        'items' => [
            ['product_id' => $this->rice->id, 'quantity' => 100],
            ['product_id' => $this->oil->id, 'quantity' => 20, 'description' => 'Sunflower, sealed'],
        ],
        'supplier_ids' => [$this->a->id, $this->b->id, $this->c->id],
    ])->assertCreated()->json('data');
});

it('creates an RFQ with a reference, its items and the suppliers asked', function () {
    expect($this->rfq['reference_number'])->toBe('RFQ-0001')
        ->and($this->rfq['status'])->toBe('draft')
        ->and($this->rfq['items'])->toHaveCount(2)
        ->and(collect($this->rfq['suppliers'])->pluck('name')->sort()->values()->all())->toBe(['Juba Traders', 'Nile Wholesale', 'No Email Co']);
});

it('emails every supplier, skips the one without an email, and marks it sent', function () {
    Mail::fake();

    $res = $this->postJson("/api/v1/rfqs/{$this->rfq['id']}/send")->assertOk();

    Mail::assertSent(DocumentMail::class, 2);
    Mail::assertSent(DocumentMail::class, fn ($m) => $m->hasTo('sales@jubatraders.test') && $m->documentType === 'Request for Quotation');
    expect($res->json('data.skipped'))->toBe(['No Email Co'])
        ->and($res->json('data.rfq.status'))->toBe('sent');
});

it('emails only the suppliers chosen', function () {
    Mail::fake();

    $this->postJson("/api/v1/rfqs/{$this->rfq['id']}/send", ['supplier_ids' => [$this->b->id]])->assertOk();

    Mail::assertSent(DocumentMail::class, 1);
    Mail::assertSent(DocumentMail::class, fn ($m) => $m->hasTo('quotes@nile.test'));
    $suppliers = collect($this->getJson("/api/v1/rfqs/{$this->rfq['id']}")->json('data.suppliers'))->keyBy('name');
    expect($suppliers['Nile Wholesale']['sent_at'])->not->toBeNull()
        ->and($suppliers['Juba Traders']['sent_at'])->toBeNull();
});

it('records quotes and converts to a purchase order at the chosen supplier\'s prices', function () {
    [$riceItem, $oilItem] = $this->rfq['items'];
    $this->postJson("/api/v1/rfqs/{$this->rfq['id']}/quote", [
        'supplier_id' => $this->b->id, 'delivery_days' => 5,
        'prices' => [['rfq_item_id' => $riceItem['id'], 'unit_price' => 38], ['rfq_item_id' => $oilItem['id'], 'unit_price' => 52.5]],
    ])->assertOk()->assertJsonPath('data.suppliers.1.quote_total', 4850);

    $po = $this->postJson("/api/v1/rfqs/{$this->rfq['id']}/convert", ['supplier_id' => $this->b->id])->assertCreated()->json('data');

    $order = PurchaseOrder::with('items')->find($po['id']);
    expect($order->supplier_id)->toBe($this->b->id)
        ->and($order->status)->toBe('draft')
        ->and($order->reference_number)->toBe('PO-RFQ-0001')
        ->and($order->items->pluck('unit_cost', 'product_id')->map(fn ($v) => (float) $v)->all())->toBe([$this->rice->id => 38.0, $this->oil->id => 52.5]);

    $rfq = $this->getJson("/api/v1/rfqs/{$this->rfq['id']}")->json('data');
    expect($rfq['status'])->toBe('converted')
        ->and($rfq['purchase_order_id'])->toBe($po['id'])
        ->and($rfq['selected_supplier_name'])->toBe('Nile Wholesale');

    // Converted: can't be converted again or edited.
    $this->postJson("/api/v1/rfqs/{$this->rfq['id']}/convert", ['supplier_id' => $this->a->id])->assertStatus(422);
    $this->putJson("/api/v1/rfqs/{$this->rfq['id']}", ['title' => 'x'])->assertStatus(422);
});

it('converts at cost price for a supplier who has not quoted', function () {
    $po = $this->postJson("/api/v1/rfqs/{$this->rfq['id']}/convert", ['supplier_id' => $this->a->id])->assertCreated()->json('data');

    expect(PurchaseOrder::with('items')->find($po['id'])->items->pluck('unit_cost', 'product_id')->map(fn ($v) => (float) $v)->all())
        ->toBe([$this->rice->id => 40.0, $this->oil->id => 55.0]);
});

it('downloads as a PDF, and only purchasing staff see RFQs', function () {
    $res = $this->get("/api/v1/rfqs/{$this->rfq['id']}/pdf?download=1")->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');

    Sanctum::actingAs(createUserWithRole('cashier', $this->company, $this->branch), ['*']);
    $this->getJson('/api/v1/rfqs')->assertForbidden();
});
