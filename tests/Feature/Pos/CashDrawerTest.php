<?php

use App\Services\Inventory\StockMovementService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->cashier = createUserWithRole('cashier', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->taxRate = createTaxRate($this->company);
    $this->paymentTypeCash = createPaymentType($this->company);
    $this->product = createProduct($this->company, ['cost_price' => 10, 'selling_price' => 20]);

    Sanctum::actingAs($this->cashier, ['*']);

    app(StockMovementService::class)->record([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'movement_type' => 'purchase',
        'quantity' => 100,
    ]);
});

it('rejects opening a second session while one is already open for the same user and branch', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 20000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('computes expected_closing and variance correctly from cash sale payments on close', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id],
        ],
    ])->json('data.id');

    $total = $this->getJson("/api/v1/sales/{$saleId}")->json('data.total_amount');

    $this->postJson("/api/v1/sales/{$saleId}/payments", [
        'payment_type_id' => $this->paymentTypeCash->id,
        'amount' => $total,
    ])->assertCreated();

    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    // Drop 500 short of the expected drawer total to produce a negative variance.
    $closingFloat = 50000 + (float) $total - 500;

    $response = $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => $closingFloat])->assertOk();

    expect((float) $response->json('data.expected_closing'))->toBe(round(50000 + (float) $total, 2));
    expect((float) $response->json('data.closing_float'))->toBe(round($closingFloat, 2));
    expect((float) $response->json('data.variance'))->toBe(-500.0);
    expect($response->json('data.status'))->toBe('closed');
});

it('shows a live expected_closing on the open session before it is closed', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $this->getJson('/api/v1/cash-drawer/current')
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.expected_closing', 50000);

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id]],
    ])->json('data.id');
    $total = $this->getJson("/api/v1/sales/{$saleId}")->json('data.total_amount');
    $this->postJson("/api/v1/sales/{$saleId}/payments", ['payment_type_id' => $this->paymentTypeCash->id, 'amount' => $total])->assertCreated();
    $this->postJson("/api/v1/sales/{$saleId}/complete")->assertOk();

    // The live figure on /current updates as cash sales come in, before any close request.
    $this->getJson('/api/v1/cash-drawer/current')
        ->assertOk()
        ->assertJsonPath('data.expected_closing', round(50000 + (float) $total, 2));
});

it('rejects closing the same session twice', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    // No open session left for this user, so the second close attempt fails
    // the same way any close-with-nothing-open request would.
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('filters cash drawer sessions by status', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])->assertCreated();
    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 20000])->assertCreated();

    $this->getJson('/api/v1/cash-drawer-sessions?status=open')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.opening_float', 20000);

    $this->getJson('/api/v1/cash-drawer-sessions?status=closed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.opening_float', 10000);
});

it('filters sales by cash_drawer_session_id so a shift report can list exactly that shift\'s receipts', function () {
    $sessionId = $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->json('data.id');

    $saleId = $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id]],
    ])->json('data.id');

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    // A sale rung up under a different (second) session must not show up
    // when filtering for the first session's receipts.
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 5000])->assertCreated();
    $this->postJson('/api/v1/sales', [
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 20, 'tax_rate_id' => $this->taxRate->id]],
    ])->assertCreated();

    $this->getJson("/api/v1/sales?cash_drawer_session_id={$sessionId}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $saleId);
});

it('allows opening a new session again after the previous one is closed', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 10000])
        ->assertCreated();

    $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 10000])->assertOk();

    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 15000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');
});

it('posts a journal entry debiting Cash Short/Over and crediting Cash for a shortage, and it shows up on the Trial Balance and P&L', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 500000])
        ->assertCreated();

    // Close 414500 short of the opening float (no sales this session, so
    // expected_closing === opening_float) — mirrors the real shortage
    // reported in production. closing_float can't go negative, hence the
    // larger opening float here versus the other scenarios in this file.
    $closingFloat = 500000 - 414500;

    $response = $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => $closingFloat])->assertOk();

    expect((float) $response->json('data.variance'))->toBe(-414500.0);
    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();

    $this->assertDatabaseHas('journal_entries', [
        'id' => $journalEntryId,
        'status' => 'posted',
        'source_module' => 'pos_cash_drawer',
    ]);

    $cashAccount = $this->accounts['1000'];
    $shortOverAccount = App\Models\ChartOfAccount::where('company_id', $this->company->id)->where('code', '5400')->firstOrFail();

    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $journalEntryId,
        'account_id' => $shortOverAccount->id,
        'debit' => 414500,
        'credit' => 0,
    ]);
    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $journalEntryId,
        'account_id' => $cashAccount->id,
        'debit' => 0,
        'credit' => 414500,
    ]);

    // Confirm it actually shows up correctly on the reports, not just in the raw tables.
    $admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($admin, ['*']);
    $today = now()->toDateString();

    $trialBalance = $this->getJson("/api/v1/reports/trial-balance?from={$today}&to={$today}")->assertOk()->json('data');
    $byCode = collect($trialBalance['accounts'])->keyBy('code');
    expect((float) $byCode['1000']['balance'])->toBe(-414500.0);
    expect((float) $byCode['5400']['balance'])->toBe(414500.0);
    expect((float) $trialBalance['total_debit'])->toBe((float) $trialBalance['total_credit']);

    $profitAndLoss = $this->getJson("/api/v1/reports/profit-and-loss?from={$today}&to={$today}")->assertOk()->json('data');
    $opEx = collect($profitAndLoss['operating_expenses']['accounts'])->keyBy('code');
    expect((float) $opEx['5400']['amount'])->toBe(414500.0);
    expect((float) $profitAndLoss['net_profit'])->toBe(-414500.0);
});

it('posts a journal entry debiting Cash and crediting Cash Short/Over for an overage', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $closingFloat = 50000 + 2000;

    $response = $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => $closingFloat])->assertOk();

    expect((float) $response->json('data.variance'))->toBe(2000.0);
    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();

    $cashAccount = $this->accounts['1000'];
    $shortOverAccount = App\Models\ChartOfAccount::where('company_id', $this->company->id)->where('code', '5400')->firstOrFail();

    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $journalEntryId,
        'account_id' => $cashAccount->id,
        'debit' => 2000,
        'credit' => 0,
    ]);
    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $journalEntryId,
        'account_id' => $shortOverAccount->id,
        'debit' => 0,
        'credit' => 2000,
    ]);
});

it('posts no journal entry when the drawer closes with exactly zero variance', function () {
    $this->postJson('/api/v1/cash-drawer/open', ['branch_id' => $this->branch->id, 'opening_float' => 50000])
        ->assertCreated();

    $response = $this->postJson('/api/v1/cash-drawer/close', ['closing_float' => 50000])->assertOk();

    expect((float) $response->json('data.variance'))->toBe(0.0);
    expect($response->json('data.journal_entry_id'))->toBeNull();

    expect(App\Models\JournalEntry::where('source_module', 'pos_cash_drawer')->count())->toBe(0);
});
