<?php

use App\Models\BudgetLine;
use App\Models\BudgetPeriod;
use App\Models\CostCentre;
use App\Models\Donor;
use App\Models\ExchangeRate;
use App\Models\Grant;
use App\Models\GrantDea;
use App\Models\JournalEntry;
use App\Models\MeProject;
use Laravel\Sanctum\Sanctum;

// Base currency USD. Rates per 1 USD: SSP 8,000 and EUR 0.9 from 1 March 2026.
// SOF-100 is a USD award; SOF-200 a EUR award (ECHO-style).
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);

    ExchangeRate::create(['company_id' => $this->company->id, 'currency_code' => 'SSP', 'rate' => 8000, 'effective_date' => '2026-03-01']);
    ExchangeRate::create(['company_id' => $this->company->id, 'currency_code' => 'EUR', 'rate' => 0.9, 'effective_date' => '2026-03-01']);

    $this->donor = Donor::create(['company_id' => $this->company->id, 'name' => 'Donor A']);
    $this->sof = Grant::create([
        'company_id' => $this->company->id, 'donor_id' => $this->donor->id, 'code' => 'SOF-100', 'name' => 'Education award',
        'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'budget_amount' => 10000, 'status' => 'active',
    ]);
    $this->dea = GrantDea::create(['company_id' => $this->company->id, 'grant_id' => $this->sof->id, 'code' => 'DEA-01', 'name' => 'Teacher training']);
    $this->eurSof = Grant::create([
        'company_id' => $this->company->id, 'donor_id' => $this->donor->id, 'code' => 'SOF-200', 'name' => 'Health award',
        'currency_code' => 'EUR', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'budget_amount' => 9000, 'status' => 'active',
    ]);
    $this->costCentre = CostCentre::create(['company_id' => $this->company->id, 'code' => 'CC-JUB', 'name' => 'Juba office']);
    $this->project = MeProject::create(['company_id' => $this->company->id, 'code' => 'PRJ-1', 'name' => 'Back to school', 'start_date' => '2026-01-01', 'status' => 'ongoing']);
});

/** Creates and posts a journal entry; returns its id. */
function postCodedEntry($test, array $lines, array $overrides = []): int
{
    $id = $test->postJson('/api/v1/journal-entries', array_merge([
        'reference_number' => 'JE-'.uniqid(),
        'entry_date' => '2026-03-15',
        'lines' => $lines,
    ], $overrides))->assertCreated()->json('data.id');

    $test->postJson("/api/v1/journal-entries/{$id}/post")->assertOk();

    return $id;
}

function expenseLines($test, float $amount, array $coding): array
{
    return [
        ['account_id' => $test->accounts['5200']->id, 'debit' => $amount, 'credit' => 0] + $coding,
        ['account_id' => $test->accounts['1000']->id, 'debit' => 0, 'credit' => $amount],
    ];
}

// ── Setup: donors, SOFs, DEAs, cost centres ────────────────────────────

it('manages donors, SOFs with their DEAs and budget, and cost centres', function () {
    $donorId = $this->postJson('/api/v1/donors', ['name' => 'ECHO', 'code' => 'ECHO'])->assertCreated()->json('data.id');

    $grant = $this->postJson('/api/v1/grants', [
        'donor_id' => $donorId, 'code' => 'SOF-300', 'name' => 'Nutrition', 'currency_code' => 'eur',
        'start_date' => '2026-01-01', 'end_date' => '2027-06-30', 'budget_amount' => 50000,
    ])->assertCreated()->assertJsonPath('data.currency_code', 'EUR')->assertJsonPath('data.status', 'active')->json('data');

    $this->postJson('/api/v1/grants', [
        'donor_id' => $donorId, 'code' => 'SOF-300', 'name' => 'Duplicate', 'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ])->assertStatus(422)->assertJsonValidationErrors('code');

    $deaId = $this->postJson("/api/v1/grants/{$grant['id']}/deas", ['code' => 'D1', 'name' => 'Screening'])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/grants/{$grant['id']}/budget-lines", ['account_id' => $this->accounts['5200']->id, 'dea_id' => $deaId, 'budgeted_amount' => 1200])->assertCreated();
    // Saving the same account + DEA again replaces the amount.
    $this->postJson("/api/v1/grants/{$grant['id']}/budget-lines", ['account_id' => $this->accounts['5200']->id, 'dea_id' => $deaId, 'budgeted_amount' => 1500])->assertCreated();
    // A DEA of another SOF can't be budgeted here.
    $this->postJson("/api/v1/grants/{$grant['id']}/budget-lines", ['account_id' => $this->accounts['5200']->id, 'dea_id' => $this->dea->id, 'budgeted_amount' => 1])
        ->assertStatus(422)->assertJsonValidationErrors('dea_id');

    $this->getJson("/api/v1/grants/{$grant['id']}")->assertOk()
        ->assertJsonPath('data.donor_name', 'ECHO')
        ->assertJsonPath('data.deas.0.code', 'D1')
        ->assertJsonCount(1, 'data.budget_lines')
        ->assertJsonPath('data.budget_lines.0.budgeted_amount', 1500);

    $this->postJson('/api/v1/cost-centres', ['code' => 'CC-WAU', 'name' => 'Wau field office'])->assertCreated();
    $this->postJson('/api/v1/cost-centres', ['code' => 'CC-WAU', 'name' => 'Again'])->assertStatus(422)->assertJsonValidationErrors('code');

    $options = $this->getJson('/api/v1/dimensions/options')->assertOk()->json('data');
    expect(collect($options['grants'])->pluck('code')->all())->toContain('SOF-100', 'SOF-200', 'SOF-300')
        ->and(collect($options['grants'])->firstWhere('code', 'SOF-300')['deas'][0]['code'])->toBe('D1')
        ->and(collect($options['cost_centres'])->pluck('code')->all())->toContain('CC-JUB', 'CC-WAU')
        ->and($options['projects'][0]['code'])->toBe('PRJ-1');
});

it('keeps SOFs per company and requires the grants permission to manage them', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    Sanctum::actingAs(createUserWithRole('company_admin', $otherCompany, $otherBranch), ['*']);

    $this->getJson("/api/v1/grants/{$this->sof->id}")->assertNotFound();
    expect($this->getJson('/api/v1/grants')->json('meta.total'))->toBe(0)
        ->and($this->getJson('/api/v1/dimensions/options')->json('data.grants'))->toBe([]);

    Sanctum::actingAs(createUserWithRole('cashier', $this->company, $this->branch), ['*']);
    $this->postJson('/api/v1/grants', ['donor_id' => $this->donor->id, 'code' => 'X', 'name' => 'X', 'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
        ->assertForbidden();
    // Anyone who codes a transaction can still read the choices.
    $this->getJson('/api/v1/dimensions/options')->assertOk();
});

// ── Coding transactions ────────────────────────────────────────────────

it('keeps the currency, rate and base amount on every coded journal line', function () {
    $entryId = postCodedEntry($this, expenseLines($this, 800000, [
        'grant_id' => $this->sof->id, 'dea_id' => $this->dea->id, 'project_id' => $this->project->id, 'cost_centre_id' => $this->costCentre->id,
    ]), ['currency_code' => 'SSP']);

    $line = JournalEntry::findOrFail($entryId)->lines->firstWhere('account_id', $this->accounts['5200']->id);
    expect((float) $line->transaction_debit)->toBe(800000.0)
        ->and((float) $line->debit)->toBe(100.0)
        ->and($line->grant_id)->toBe($this->sof->id)
        ->and($line->dea_id)->toBe($this->dea->id)
        ->and($line->project_id)->toBe($this->project->id)
        ->and($line->cost_centre_id)->toBe($this->costCentre->id);

    $this->getJson("/api/v1/journal-entries/{$entryId}")->assertOk()
        ->assertJsonPath('data.currency_code', 'SSP')
        ->assertJsonPath('data.exchange_rate', 8000)
        ->assertJsonPath('data.lines.0.grant_code', 'SOF-100')
        ->assertJsonPath('data.lines.0.dea_code', 'DEA-01')
        ->assertJsonPath('data.lines.0.cost_centre_code', 'CC-JUB');

    $ledger = $this->getJson('/api/v1/reports/general-ledger?account_id='.$this->accounts['5200']->id.'&from=2026-03-01&to=2026-03-31')->json('data.lines.0');
    expect($ledger['currency_code'])->toBe('SSP')
        ->and($ledger['exchange_rate'])->toEqual(8000)
        ->and($ledger['transaction_debit'])->toEqual(800000)
        ->and($ledger['debit'])->toEqual(100)
        ->and($ledger['grant_code'])->toBe('SOF-100')
        ->and($ledger['project_code'])->toBe('PRJ-1');
});

it('rejects coding that breaks the SOF rules', function () {
    $otherDea = GrantDea::create(['company_id' => $this->company->id, 'grant_id' => $this->eurSof->id, 'code' => 'DEA-X', 'name' => 'Other']);
    $post = fn (array $coding, string $date = '2026-03-15') => $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-'.uniqid(), 'entry_date' => $date, 'lines' => expenseLines($this, 50, $coding),
    ]);

    // A DEA must belong to the SOF on the same line.
    $post(['grant_id' => $this->sof->id, 'dea_id' => $otherDea->id])->assertStatus(422)->assertJsonValidationErrors('lines.0.dea_id');
    $post(['dea_id' => $this->dea->id])->assertStatus(422)->assertJsonValidationErrors('lines.0.dea_id');
    // Only within the SOF's dates.
    $post(['grant_id' => $this->sof->id], '2027-01-05')->assertStatus(422)->assertJsonValidationErrors('lines.0.grant_id');
    // Not a closed SOF.
    $this->sof->update(['status' => 'closed']);
    $post(['grant_id' => $this->sof->id])->assertStatus(422)->assertJsonValidationErrors('lines.0.grant_id');
    // Not another company's.
    [$otherCompany] = createCompanyWithMainBranch();
    $foreign = CostCentre::create(['company_id' => $otherCompany->id, 'code' => 'CC-X', 'name' => 'Theirs']);
    $post(['cost_centre_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors('lines.0.cost_centre_id');
});

it('codes a supplier bill expense line, and re-posts it when the coding changes', function () {
    $bill = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => createSupplier($this->company)->id, 'reference_number' => 'BILL-SC-1',
        'bill_date' => '2026-03-20', 'due_date' => '2026-04-20', 'subtotal' => 400, 'tax_amount' => 0,
        'grant_id' => $this->sof->id, 'dea_id' => $this->dea->id, 'cost_centre_id' => $this->costCentre->id,
    ])->assertCreated()->assertJsonPath('data.grant_code', 'SOF-100')->json('data');

    $expense = fn () => JournalEntry::findOrFail(\App\Models\SupplierBill::findOrFail($bill['id'])->journal_entry_id)
        ->lines->firstWhere('account_id', $this->accounts['5200']->id);
    expect($expense()->grant_id)->toBe($this->sof->id)->and($expense()->dea_id)->toBe($this->dea->id);

    $this->putJson("/api/v1/supplier-bills/{$bill['id']}", ['grant_id' => $this->eurSof->id, 'dea_id' => null])->assertOk();
    expect($expense()->grant_id)->toBe($this->eurSof->id);

    $sofReport = $this->getJson("/api/v1/grants/{$this->sof->id}/report")->json('data.totals');
    expect($sofReport['actual_base'])->toEqual(0);

    $this->putJson("/api/v1/supplier-bills/{$bill['id']}", ['grant_id' => $this->sof->id, 'dea_id' => $this->eurSof->deas()->create(['company_id' => $this->company->id, 'code' => 'Z', 'name' => 'Z'])->id])
        ->assertStatus(422)->assertJsonValidationErrors('dea_id');
});

it('splits invoice revenue by the coding on its items', function () {
    $customer = createCustomer($this->company);
    $product = createProduct($this->company, ['selling_price' => 100]);
    $invoiceId = $this->postJson('/api/v1/invoices', [
        'branch_id' => $this->branch->id, 'customer_id' => $customer->id,
        'invoice_date' => '2026-03-15', 'due_date' => '2026-04-15', 'discount_amount' => 30,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'grant_id' => $this->sof->id],
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'project_id' => $this->project->id],
        ],
    ])->assertCreated()->assertJsonPath('data.items.0.grant_code', 'SOF-100')->json('data.id');
    $this->postJson("/api/v1/invoices/{$invoiceId}/send")->assertOk();

    $revenue = JournalEntry::where('source_module', 'sales')->firstOrFail()->lines->where('account_id', $this->accounts['4000']->id);
    expect($revenue)->toHaveCount(2)
        ->and((float) $revenue->firstWhere('grant_id', $this->sof->id)->credit)->toBe(180.0)
        ->and((float) $revenue->firstWhere('project_id', $this->project->id)->credit)->toBe(90.0);
});

it('carries purchase order line coding through goods received into the ledger', function () {
    $warehouse = createWarehouse($this->company, $this->branch);
    $supplier = createSupplier($this->company);
    $product = createProduct($this->company);
    $po = $this->postJson('/api/v1/purchase-orders', [
        'branch_id' => $this->branch->id, 'warehouse_id' => $warehouse->id, 'supplier_id' => $supplier->id,
        'reference_number' => 'PO-SC-1', 'order_date' => '2026-03-10',
        'items' => [
            ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 5, 'grant_id' => $this->sof->id, 'dea_id' => $this->dea->id],
            ['product_id' => $product->id, 'quantity_ordered' => 4, 'unit_cost' => 5, 'cost_centre_id' => $this->costCentre->id],
        ],
    ])->assertCreated()->assertJsonPath('data.items.0.dea_code', 'DEA-01')->json('data');
    \App\Models\PurchaseOrder::findOrFail($po['id'])->update(['status' => 'approved']);

    $grnId = $this->postJson('/api/v1/goods-received-notes', [
        'warehouse_id' => $warehouse->id, 'supplier_id' => $supplier->id, 'purchase_order_id' => $po['id'],
        'reference_number' => 'GRN-SC-1', 'received_date' => '2026-03-12',
        'items' => collect($po['items'])->map(fn ($item) => [
            'product_id' => $product->id, 'purchase_order_item_id' => $item['id'], 'quantity_received' => $item['quantity_ordered'], 'unit_cost' => 5,
        ])->all(),
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/goods-received-notes/{$grnId}/confirm")->assertOk();

    $inventory = JournalEntry::where('reference_number', 'GRN-JE-GRN-SC-1')->firstOrFail()->lines->where('account_id', $this->accounts['1200']->id);
    expect((float) $inventory->firstWhere('grant_id', $this->sof->id)->debit)->toBe(50.0)
        ->and($inventory->firstWhere('grant_id', $this->sof->id)->dea_id)->toBe($this->dea->id)
        ->and((float) $inventory->firstWhere('cost_centre_id', $this->costCentre->id)->debit)->toBe(20.0);
});

// ── Dimension reports ──────────────────────────────────────────────────

it('filters the trial balance, P&L and general ledger by SOF, project and cost centre', function () {
    postCodedEntry($this, expenseLines($this, 300, ['grant_id' => $this->sof->id, 'cost_centre_id' => $this->costCentre->id]));
    postCodedEntry($this, expenseLines($this, 200, ['project_id' => $this->project->id]));
    postCodedEntry($this, expenseLines($this, 50, []));

    $query = 'from=2026-03-01&to=2026-03-31';
    expect($this->getJson("/api/v1/reports/profit-and-loss?{$query}")->json('data.operating_expenses.total'))->toEqual(550)
        ->and($this->getJson("/api/v1/reports/profit-and-loss?{$query}&grant_id={$this->sof->id}")->json('data.operating_expenses.total'))->toEqual(300)
        ->and($this->getJson("/api/v1/reports/profit-and-loss?{$query}&project_id={$this->project->id}")->json('data.operating_expenses.total'))->toEqual(200)
        ->and($this->getJson("/api/v1/reports/profit-and-loss?{$query}&grant_id={$this->sof->id}&project_id={$this->project->id}")->json('data.operating_expenses.total'))->toEqual(0);

    $trial = collect($this->getJson("/api/v1/reports/trial-balance?{$query}&cost_centre_id={$this->costCentre->id}")->json('data.accounts'));
    expect($trial->firstWhere('code', '5200')['debit'])->toEqual(300)->and($trial->firstWhere('code', '1000'))->toBeNull();

    $ledger = $this->getJson("/api/v1/reports/general-ledger?account_id={$this->accounts['5200']->id}&{$query}&project_id={$this->project->id}")->json('data');
    expect($ledger['lines'])->toHaveCount(1)->and($ledger['closing_balance'])->toEqual(200);

    // Converted like any report: 300 USD at 8,000 SSP.
    $converted = $this->getJson("/api/v1/reports/profit-and-loss?{$query}&grant_id={$this->sof->id}&currency=SSP")->json('data');
    expect($converted['operating_expenses']['total'])->toEqual(2400000)->and($converted['currency']['converted'])->toBeTrue();
});

it('compares budget lines coded to a SOF with the spending coded to it', function () {
    $period = BudgetPeriod::create(['company_id' => $this->company->id, 'name' => 'FY26', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    BudgetLine::create(['budget_period_id' => $period->id, 'account_id' => $this->accounts['5200']->id, 'budgeted_amount' => 1000, 'grant_id' => $this->sof->id]);
    BudgetLine::create(['budget_period_id' => $period->id, 'account_id' => $this->accounts['5200']->id, 'budgeted_amount' => 5000]);
    postCodedEntry($this, expenseLines($this, 250, ['grant_id' => $this->sof->id]));
    postCodedEntry($this, expenseLines($this, 100, []));

    $all = $this->getJson("/api/v1/budget-periods/{$period->id}/vs-actual")->assertOk()->json('data');
    expect($all['lines'])->toHaveCount(2)
        ->and(collect($all['lines'])->firstWhere('grant_id', $this->sof->id)['actual_amount'])->toEqual(250)
        ->and(collect($all['lines'])->firstWhere('grant_id', null)['actual_amount'])->toEqual(350);

    $sof = $this->getJson("/api/v1/budget-periods/{$period->id}/vs-actual?grant_id={$this->sof->id}&currency=SSP")->assertOk()->json('data');
    expect($sof['lines'])->toHaveCount(1)
        ->and($sof['lines'][0]['grant_code'])->toBe('SOF-100')
        ->and($sof['totals']['actual_amount'])->toEqual(2000000)
        ->and($sof['currency']['code'])->toBe('SSP');
});

it('reports a SOF in its own currency alongside base, by account and DEA', function () {
    $this->eurSof->budgetLines()->create(['account_id' => $this->accounts['5200']->id, 'budgeted_amount' => 1000]);
    $this->eurSof->budgetLines()->create(['account_id' => $this->accounts['5000']->id, 'budgeted_amount' => 500]);
    // 90 EUR transacted in EUR: counts as exactly 90 EUR (base 100 USD).
    postCodedEntry($this, expenseLines($this, 90, ['grant_id' => $this->eurSof->id]), ['currency_code' => 'EUR']);
    // 200 USD: 180 EUR at the rate on its date.
    postCodedEntry($this, expenseLines($this, 200, ['grant_id' => $this->eurSof->id]));
    // 800,000 SSP = 100 USD = 90 EUR.
    postCodedEntry($this, expenseLines($this, 800000, ['grant_id' => $this->eurSof->id]), ['currency_code' => 'SSP']);
    // Not this SOF.
    postCodedEntry($this, expenseLines($this, 999, ['grant_id' => $this->sof->id]));

    $report = $this->getJson("/api/v1/grants/{$this->eurSof->id}/report")->assertOk()->json('data');
    $row = collect($report['rows'])->firstWhere('account_id', $this->accounts['5200']->id);

    expect($report['sof_currency'])->toBe('EUR')
        ->and($report['base_currency'])->toBe('USD')
        ->and($row['budget_sof'])->toEqual(1000)
        ->and($row['actual_sof'])->toEqual(360)
        ->and($row['actual_base'])->toEqual(400)
        ->and($row['variance_sof'])->toEqual(640)
        ->and($row['utilization_percent'])->toEqual(36)
        ->and(collect($report['rows'])->firstWhere('account_id', $this->accounts['5000']->id)['actual_sof'])->toEqual(0)
        ->and($report['totals']['budget_sof'])->toEqual(1500)
        ->and($report['totals']['award_sof'])->toEqual(9000)
        ->and($report['totals']['remaining_award_sof'])->toEqual(8640)
        ->and($report['transactions'])->toHaveCount(3)
        ->and($report['missing_rates'])->toBe([]);

    // "Show in SSP" converts the base columns only; the SOF's stay in EUR.
    $inSsp = $this->getJson("/api/v1/grants/{$this->eurSof->id}/report?currency=SSP")->json('data');
    $sspRow = collect($inSsp['rows'])->firstWhere('account_id', $this->accounts['5200']->id);
    expect($sspRow['actual_base'])->toEqual(3200000)
        ->and($sspRow['actual_sof'])->toEqual(360)
        ->and($inSsp['currency']['converted'])->toBeTrue();

    $summary = collect($this->getJson('/api/v1/grants/summary')->assertOk()->json('data'))->firstWhere('grant_id', $this->eurSof->id);
    expect($summary['actual_sof'])->toEqual(360)->and($summary['burn_percent'])->toEqual(4);
});

it('reports DEAs separately and nets a reversed entry out of the SOF', function () {
    $this->sof->budgetLines()->create(['account_id' => $this->accounts['5200']->id, 'dea_id' => $this->dea->id, 'budgeted_amount' => 700]);
    postCodedEntry($this, expenseLines($this, 120, ['grant_id' => $this->sof->id, 'dea_id' => $this->dea->id]));
    postCodedEntry($this, expenseLines($this, 30, ['grant_id' => $this->sof->id]));
    $reversed = postCodedEntry($this, expenseLines($this, 75, ['grant_id' => $this->sof->id, 'dea_id' => $this->dea->id]));
    $this->postJson("/api/v1/journal-entries/{$reversed}/reverse")->assertCreated();

    $rows = collect($this->getJson("/api/v1/grants/{$this->sof->id}/report")->json('data.rows'));

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('dea_id', $this->dea->id)['actual_sof'])->toEqual(120)
        ->and($rows->firstWhere('dea_id', $this->dea->id)['budget_sof'])->toEqual(700)
        ->and($rows->firstWhere('dea_id', null)['actual_sof'])->toEqual(30);
});

it('will not delete a SOF or cost centre that transactions are coded to', function () {
    postCodedEntry($this, expenseLines($this, 10, ['grant_id' => $this->sof->id, 'cost_centre_id' => $this->costCentre->id]));

    $this->deleteJson("/api/v1/grants/{$this->sof->id}")->assertStatus(422)->assertJsonValidationErrors('grant');
    $this->deleteJson("/api/v1/cost-centres/{$this->costCentre->id}")->assertStatus(422)->assertJsonValidationErrors('cost_centre');
    $this->deleteJson("/api/v1/grant-deas/{$this->eurSof->deas()->create(['company_id' => $this->company->id, 'code' => 'U', 'name' => 'Unused'])->id}")->assertOk();
    $this->deleteJson("/api/v1/grants/{$this->eurSof->id}")->assertOk();
});
