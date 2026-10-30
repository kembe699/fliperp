<?php

use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\JournalEntryLine;
use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);

    $account = fn (string $code, string $name, string $type) => ChartOfAccount::create([
        'company_id' => $this->company->id, 'code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true,
    ]);
    $this->drugRevenue = $account('4010', 'Pharmacy Revenue', 'revenue');
    $this->drugCost = $account('5310', 'Cost of Drugs', 'expense');
    $this->drugStock = $account('1210', 'Drug Inventory', 'asset');
    $this->serviceRevenue = $account('4020', 'Consultation Revenue', 'revenue');

    $this->pharmacy = Department::create(['company_id' => $this->company->id, 'name' => 'Pharmacy']);
    $this->clinic = Department::create(['company_id' => $this->company->id, 'name' => 'Clinic']);

    $this->drugs = createCategory($this->company, [
        'name' => 'Drugs',
        'revenue_account_id' => $this->drugRevenue->id,
        'cogs_account_id' => $this->drugCost->id,
        'inventory_account_id' => $this->drugStock->id,
    ]);
    $this->services = createCategory($this->company, ['name' => 'Consultations', 'revenue_account_id' => $this->serviceRevenue->id]);

    $this->drug = createProduct($this->company, ['category' => $this->drugs, 'department' => $this->pharmacy, 'cost_price' => 30, 'selling_price' => 100]);
    $this->consult = createProduct($this->company, ['category' => $this->services, 'department' => $this->clinic, 'cost_price' => 0, 'selling_price' => 50, 'track_inventory' => false]);
});

function linesOf(int $journalEntryId): array
{
    return JournalEntryLine::where('journal_entry_id', $journalEntryId)->get()
        ->map(fn ($line) => [$line->account_id, (float) $line->debit, (float) $line->credit, $line->department_id])
        ->all();
}

it('stores a category\'s accounts and rejects an account of the wrong type', function () {
    $category = $this->postJson('/api/v1/categories', [
        'name' => 'Lab',
        'revenue_account_id' => $this->serviceRevenue->id,
        'cogs_account_id' => $this->drugCost->id,
        'inventory_account_id' => $this->drugStock->id,
    ])->assertCreated()->json('data');

    expect($category['revenue_account_id'])->toBe($this->serviceRevenue->id)
        ->and($category['inventory_account_id'])->toBe($this->drugStock->id);

    $this->postJson('/api/v1/categories', ['name' => 'Bad', 'revenue_account_id' => $this->drugCost->id])
        ->assertStatus(422)->assertJsonValidationErrors('revenue_account_id');
});

it('requires a department on every product or service', function () {
    $unit = createUnitOfMeasure($this->company);
    $payload = ['category_id' => $this->drugs->id, 'name' => 'Paracetamol', 'sku' => 'PCM', 'unit_of_measure_id' => $unit->id, 'cost_price' => 1, 'selling_price' => 2];

    $this->postJson('/api/v1/products', $payload)->assertStatus(422)->assertJsonValidationErrors('department_id');

    $product = $this->postJson('/api/v1/products', $payload + ['department_id' => $this->pharmacy->id])->assertCreated()->json('data');
    expect($product['department_id'])->toBe($this->pharmacy->id);
});

it('posts an invoice\'s revenue to each item\'s category account and department', function () {
    $invoice = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => createCustomer($this->company)->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            ['product_id' => $this->drug->id, 'quantity' => 2, 'unit_price' => 100],
            ['product_id' => $this->consult->id, 'quantity' => 1, 'unit_price' => 50],
        ],
    ])->assertCreated()->json('data');

    $sent = $this->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk()->json('data');

    expect(linesOf($sent['journal_entry_id']))->toEqualCanonicalizing([
        [$this->accounts['1100']->id, 250.0, 0.0, null],
        [$this->drugRevenue->id, 0.0, 200.0, $this->pharmacy->id],
        [$this->serviceRevenue->id, 0.0, 50.0, $this->clinic->id],
    ]);

    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfMonth()->toDateString();
    $clinicOnly = $this->getJson("/api/v1/reports/profit-and-loss?from={$from}&to={$to}&department_id={$this->clinic->id}")->assertOk()->json('data');
    expect($clinicOnly['revenue']['total'])->toEqual(50);
});

it('debits received stock to the category\'s inventory account under its department', function () {
    $warehouse = createWarehouse($this->company, $this->branch);
    $grnId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $warehouse->id,
        'supplier_id' => createSupplier($this->company)->id,
        'reference_number' => 'GRN-CAT-1',
        'received_date' => now()->toDateString(),
        'items' => [['product_id' => $this->drug->id, 'quantity_received' => 10, 'unit_cost' => 30, 'condition' => 'good']],
    ])->json('data.id');

    $this->postJson("/api/v1/goods-received-notes/{$grnId}/confirm")->assertOk();

    $line = JournalEntryLine::where('account_id', $this->drugStock->id)->sole();
    expect((float) $line->debit)->toBe(300.0)->and($line->department_id)->toBe($this->pharmacy->id);
});

it('posts a POS sale to category revenue, cost and inventory accounts, and reports that cost as COGS', function () {
    $warehouse = createWarehouse($this->company, $this->branch);
    app(StockMovementService::class)->record(['product_id' => $this->drug->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'purchase', 'quantity' => 10]);

    $sale = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $warehouse->id,
        'items' => [
            ['product_id' => $this->drug->id, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $this->consult->id, 'quantity' => 1, 'unit_price' => 50],
        ],
    ])->assertCreated()->json('data');
    $this->postJson("/api/v1/sales/{$sale['id']}/payments", ['payment_type_id' => createPaymentType($this->company)->id, 'amount' => $sale['total_amount']])->assertCreated();
    $completed = $this->postJson("/api/v1/sales/{$sale['id']}/complete")->assertOk()->json('data');

    expect(linesOf($completed['journal_entry_id']))->toEqualCanonicalizing([
        [$this->accounts['1000']->id, 150.0, 0.0, null],
        [$this->drugRevenue->id, 0.0, 100.0, $this->pharmacy->id],
        [$this->serviceRevenue->id, 0.0, 50.0, $this->clinic->id],
        [$this->drugCost->id, 30.0, 0.0, $this->pharmacy->id],
        [$this->drugStock->id, 0.0, 30.0, $this->pharmacy->id],
    ]);

    $pl = $this->getJson('/api/v1/reports/profit-and-loss?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())->json('data');
    expect($pl['cogs']['total'])->toEqual(30)
        ->and($pl['gross_profit'])->toEqual(120)
        ->and(collect($pl['operating_expenses']['accounts'])->pluck('account_id'))->not->toContain($this->drugCost->id);
});

it('falls back to the default accounts when a category has none', function () {
    $plain = createProduct($this->company, ['department' => $this->clinic, 'selling_price' => 40]);
    $invoice = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id,
        'customer_id' => createCustomer($this->company)->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [['product_id' => $plain->id, 'quantity' => 1, 'unit_price' => 40]],
    ])->json('data');

    $sent = $this->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk()->json('data');

    expect(linesOf($sent['journal_entry_id']))->toContain([$this->accounts['4000']->id, 0.0, 40.0, $this->clinic->id]);
});

it('will not delete a department that still has products', function () {
    $this->deleteJson("/api/v1/departments/{$this->pharmacy->id}")->assertStatus(422);

    $empty = Department::create(['company_id' => $this->company->id, 'name' => 'Empty']);
    $this->deleteJson("/api/v1/departments/{$empty->id}")->assertOk();
});
