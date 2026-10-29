<?php

use App\Models\ChartOfAccount;
use App\Models\ExchangeRate;
use App\Models\JournalEntry;
use Laravel\Sanctum\Sanctum;

// Base currency USD (the company factory's default). Rates are SSP per 1 USD:
// 7,800 from 1 March, 8,000 from 10 March.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);

    ExchangeRate::create(['company_id' => $this->company->id, 'currency_code' => 'SSP', 'rate' => 7800, 'effective_date' => '2026-03-01']);
    ExchangeRate::create(['company_id' => $this->company->id, 'currency_code' => 'SSP', 'rate' => 8000, 'effective_date' => '2026-03-10']);
});

function linesByCode(int $entryId): array
{
    return JournalEntry::with('lines.account')->findOrFail($entryId)->lines
        ->groupBy(fn ($line) => $line->account->code)
        ->map(fn ($lines) => [
            'debit' => round((float) $lines->sum('debit'), 2),
            'credit' => round((float) $lines->sum('credit'), 2),
            'transaction_debit' => round((float) $lines->sum('transaction_debit'), 2),
            'transaction_credit' => round((float) $lines->sum('transaction_credit'), 2),
        ])->all();
}

// ── Exchange rates ─────────────────────────────────────────────────────

it('manages exchange rates per currency and date', function () {
    $response = $this->postJson('/api/v1/exchange-rates', ['currency_code' => 'ssp', 'rate' => 8100, 'effective_date' => '2026-03-15']);

    $response->assertCreated()
        ->assertJsonPath('data.currency_code', 'SSP')
        ->assertJsonPath('data.rate', 8100);

    // One rate per currency per date.
    $this->postJson('/api/v1/exchange-rates', ['currency_code' => 'SSP', 'rate' => 8200, 'effective_date' => '2026-03-15'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('effective_date');

    $this->putJson('/api/v1/exchange-rates/'.$response->json('data.id'), ['rate' => 8150])
        ->assertOk()
        ->assertJsonPath('data.rate', 8150);

    expect($this->getJson('/api/v1/exchange-rates?currency_code=SSP')->json('meta.total'))->toBe(3);
});

it('rejects a rate for the base currency', function () {
    $this->postJson('/api/v1/exchange-rates', ['currency_code' => 'USD', 'rate' => 1, 'effective_date' => '2026-03-15'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('currency_code');
});

it('looks up the latest rate on or before a date', function () {
    $this->getJson('/api/v1/exchange-rates/lookup?currency_code=SSP&date=2026-03-09')
        ->assertOk()
        ->assertJsonPath('data.rate', 7800)
        ->assertJsonPath('data.effective_date', '2026-03-01');

    $this->getJson('/api/v1/exchange-rates/lookup?currency_code=SSP&date=2026-03-12')
        ->assertJsonPath('data.rate', 8000)
        ->assertJsonPath('data.effective_date', '2026-03-10');

    $this->getJson('/api/v1/exchange-rates/lookup?currency_code=SSP&date=2026-02-01')
        ->assertJsonPath('data.rate', null);

    $this->getJson('/api/v1/exchange-rates/lookup?currency_code=USD&date=2026-02-01')
        ->assertJsonPath('data.is_base', true)
        ->assertJsonPath('data.rate', 1);
});

it('keeps exchange rates per company', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    ExchangeRate::create(['company_id' => $otherCompany->id, 'currency_code' => 'SSP', 'rate' => 1, 'effective_date' => '2026-03-11']);

    $this->getJson('/api/v1/exchange-rates/lookup?currency_code=SSP&date=2026-03-12')->assertJsonPath('data.rate', 8000);
    expect($this->getJson('/api/v1/exchange-rates')->json('meta.total'))->toBe(2);
});

it('denies managing exchange rates without the permission', function () {
    Sanctum::actingAs(createUserWithRole('cashier', $this->company, $this->branch), ['*']);

    $this->postJson('/api/v1/exchange-rates', ['currency_code' => 'SSP', 'rate' => 8100, 'effective_date' => '2026-03-15'])->assertForbidden();
    // Looking up the default rate for a document is open to any signed-in user.
    $this->getJson('/api/v1/exchange-rates/lookup?currency_code=SSP&date=2026-03-12')->assertOk();
});

// ── Journal entries ────────────────────────────────────────────────────

it('stores a foreign-currency journal entry in both its own and the base currency', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-SSP-1',
        'entry_date' => '2026-03-05',
        'currency_code' => 'SSP',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 78000, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 78000],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.currency_code', 'SSP')
        ->assertJsonPath('data.exchange_rate', 7800)
        ->assertJsonPath('data.total_debit', 10)
        ->assertJsonPath('data.total_transaction_debit', 78000);

    expect(linesByCode($response->json('data.id'))['1000'])->toBe(['debit' => 10.0, 'credit' => 0.0, 'transaction_debit' => 78000.0, 'transaction_credit' => 0.0]);
});

it('uses a rate typed on the entry over the rate for its date', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-SSP-2',
        'entry_date' => '2026-03-05',
        'currency_code' => 'SSP',
        'exchange_rate' => 6500,
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 65000, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 65000],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.exchange_rate', 6500)->assertJsonPath('data.total_debit', 10);
});

it('keeps the base side balanced when converting leaves a rounding cent', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-SSP-3',
        'entry_date' => '2026-03-05',
        'currency_code' => 'SSP',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $this->accounts['1100']->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $this->accounts['1200']->id, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 30000],
        ],
    ]);

    // 3 × round(10,000 / 7,800) = 3.84 against round(30,000 / 7,800) = 3.85.
    $response->assertCreated()->assertJsonPath('data.total_debit', 3.85)->assertJsonPath('data.total_credit', 3.85);
});

it('rejects a foreign-currency entry dated before any rate exists', function () {
    $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-SSP-4',
        'entry_date' => '2026-02-01',
        'currency_code' => 'SSP',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 100],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('exchange_rate');
});

it('reverses a foreign-currency entry with the same amounts in both currencies', function () {
    $entryId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-SSP-5',
        'entry_date' => now()->toDateString(),
        'currency_code' => 'SSP',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 80000, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 80000],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/journal-entries/{$entryId}/post")->assertOk();
    $reversalId = $this->postJson("/api/v1/journal-entries/{$entryId}/reverse")->assertCreated()->json('data.id');

    expect(linesByCode($reversalId)['1000'])->toBe(['debit' => 0.0, 'credit' => 10.0, 'transaction_debit' => 0.0, 'transaction_credit' => 80000.0]);
});

it('posts base-currency entries exactly as before', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-USD-1',
        'entry_date' => '2026-03-05',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 250, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 250],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.currency_code', 'USD')
        ->assertJsonPath('data.exchange_rate', 1)
        ->assertJsonPath('data.total_debit', 250)
        ->assertJsonPath('data.total_transaction_debit', 250);
});

// ── Invoices and customer payments ─────────────────────────────────────

function sendSspInvoice($test, array $item): array
{
    $invoice = $test->postJson('/api/v1/invoices', [
        'branch_id' => $test->branch->id,
        'customer_id' => $test->customer->id,
        'invoice_date' => '2026-03-05',
        'due_date' => '2026-04-05',
        'currency_code' => 'SSP',
        'items' => [array_merge(['product_id' => $test->product->id, 'quantity' => 1], $item)],
    ])->assertCreated()->json('data');

    $test->postJson("/api/v1/invoices/{$invoice['id']}/send")->assertOk();

    return $invoice;
}

it('invoices in SSP at the rate for the invoice date and books receivable in base', function () {
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['selling_price' => 10]);

    $invoice = sendSspInvoice($this, ['unit_price' => 78000]);

    expect($invoice['currency_code'])->toBe('SSP')
        ->and($invoice['exchange_rate'])->toEqual(7800)
        ->and($invoice['total_amount'])->toEqual(78000);

    $entry = JournalEntry::where('source_module', 'sales')->where('source_id', $invoice['id'])->firstOrFail();
    expect(linesByCode($entry->id)['1100'])->toBe(['debit' => 10.0, 'credit' => 0.0, 'transaction_debit' => 78000.0, 'transaction_credit' => 0.0]);
});

it('converts a catalogue price into the invoice currency when no price is typed', function () {
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['selling_price' => 10]);

    $invoice = sendSspInvoice($this, []);

    expect($invoice['items'][0]['unit_price'])->toEqual(78000);
});

it('posts a realised exchange loss when an SSP invoice is paid after the rate moved', function () {
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['selling_price' => 10]);
    $invoice = sendSspInvoice($this, ['unit_price' => 78000]);

    $payment = $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $invoice['id'],
        'payment_type_id' => createPaymentType($this->company)->id,
        'payment_date' => '2026-03-12',
        'amount' => 78000,
    ])->assertCreated()->json('data');

    expect($payment['currency_code'])->toBe('SSP')->and($payment['exchange_rate'])->toEqual(8000);

    // Cash 78,000 / 8,000 = 9.75 in; receivable cleared at the invoice's 10.00; 0.25 lost.
    $lines = linesByCode($payment['journal_entry_id']);
    expect($lines['1000']['debit'])->toBe(9.75)
        ->and($lines['1100']['credit'])->toBe(10.0)
        ->and($lines['5500'])->toBe(['debit' => 0.25, 'credit' => 0.0, 'transaction_debit' => 0.0, 'transaction_credit' => 0.0]);

    // Receivable is fully cleared in base: 10.00 booked, 10.00 relieved.
    $receivable = $this->getJson('/api/v1/reports/trial-balance?from=2026-03-01&to=2026-03-31')->json('data.accounts');
    expect(collect($receivable)->firstWhere('code', '1100')['balance'])->toEqual(0);
    expect(ChartOfAccount::where('company_id', $this->company->id)->where('code', '5500')->value('name'))->toBe('Realised FX Gain/Loss');
});

it('posts no exchange difference when a payment uses the invoice rate', function () {
    $this->customer = createCustomer($this->company);
    $this->product = createProduct($this->company, ['selling_price' => 10]);
    $invoice = sendSspInvoice($this, ['unit_price' => 78000]);

    $payment = $this->postJson('/api/v1/customer-payments', [
        'customer_id' => $this->customer->id,
        'invoice_id' => $invoice['id'],
        'payment_type_id' => createPaymentType($this->company)->id,
        'payment_date' => '2026-03-12',
        'amount' => 78000,
        'exchange_rate' => 7800,
    ])->assertCreated()->json('data');

    expect(linesByCode($payment['journal_entry_id']))->not->toHaveKey('5500');
});

// ── Supplier bills and payments ────────────────────────────────────────

it('posts a realised exchange gain when an SSP bill is paid after the rate moved', function () {
    $supplier = createSupplier($this->company);

    $bill = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $supplier->id,
        'reference_number' => 'SSP-BILL-1',
        'bill_date' => '2026-03-05',
        'due_date' => '2026-04-05',
        'subtotal' => 80000,
        'currency_code' => 'SSP',
    ])->assertCreated()->json('data');

    expect($bill['currency_code'])->toBe('SSP')->and($bill['exchange_rate'])->toEqual(7800);

    $payment = $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $supplier->id,
        'supplier_bill_id' => $bill['id'],
        'payment_date' => '2026-03-12',
        'amount' => 80000,
    ])->assertCreated()->json('data');

    // Payable cleared at 80,000 / 7,800 = 10.26; cash out 80,000 / 8,000 = 10.00; 0.26 gained.
    $lines = linesByCode($payment['journal_entry_id']);
    expect($lines['2000']['debit'])->toBe(10.26)
        ->and($lines['1000']['credit'])->toBe(10.0)
        ->and($lines['5500'])->toBe(['debit' => 0.0, 'credit' => 0.26, 'transaction_debit' => 0.0, 'transaction_credit' => 0.0]);
});

// ── Reports ────────────────────────────────────────────────────────────

it('shows a report in another currency, marked as converted with the rate and its date', function () {
    $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-RPT-1',
        'entry_date' => '2026-03-05',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 100],
        ],
    ]);
    $entryId = JournalEntry::where('reference_number', 'JE-RPT-1')->value('id');
    Sanctum::actingAs(createUserWithRole('company_admin', $this->company, $this->branch), ['*']);
    $this->postJson("/api/v1/journal-entries/{$entryId}/post")->assertOk();

    $ledger = $this->getJson('/api/v1/reports/trial-balance?from=2026-03-01&to=2026-03-31')->assertOk();
    $ledger->assertJsonPath('data.currency.code', 'USD')->assertJsonPath('data.currency.converted', false)->assertJsonPath('data.total_debit', 100);

    // Converted at the rate in force on the report's end date (8,000 from 10 March).
    $converted = $this->getJson('/api/v1/reports/trial-balance?from=2026-03-01&to=2026-03-31&currency=SSP')->assertOk();
    $converted->assertJsonPath('data.currency.code', 'SSP')
        ->assertJsonPath('data.currency.converted', true)
        ->assertJsonPath('data.currency.rate', 8000)
        ->assertJsonPath('data.currency.rate_date', '2026-03-10')
        ->assertJsonPath('data.total_debit', 800000);

    $this->getJson('/api/v1/reports/profit-and-loss?from=2026-03-01&to=2026-03-31&currency=SSP')
        ->assertOk()
        ->assertJsonPath('data.net_profit', 800000);

    $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-03-05&currency=SSP')
        ->assertOk()
        ->assertJsonPath('data.currency.rate', 7800)
        ->assertJsonPath('data.assets.total', 780000);
});

it('refuses to convert a report when no rate exists for its date', function () {
    $this->getJson('/api/v1/reports/trial-balance?from=2026-01-01&to=2026-01-31&currency=SSP')
        ->assertStatus(422)
        ->assertJsonValidationErrors('currency');
});
