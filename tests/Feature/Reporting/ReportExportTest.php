<?php

use App\Models\BudgetPeriod;
use App\Models\Grant;
use App\Models\Donor;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;

// G3: reports download as CSV, Excel and PDF with the same figures as the screen.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

/** Rows of a CSV download, header first. */
function csvRows($response): array
{
    $body = $response->streamedContent();
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

    return array_map('str_getcsv', array_values(array_filter(explode("\n", trim($body)))));
}

/** Every row of the first sheet of an Excel download. */
function xlsxRows($response): array
{
    $path = $response->baseResponse->getFile()->getPathname();

    return IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
}

/** The value in $column of the row whose $labelColumn reads $label. */
function rowValue(array $rows, int $labelColumn, string $label, int $column): mixed
{
    foreach ($rows as $row) {
        if (($row[$labelColumn] ?? null) === $label) {
            return $row[$column];
        }
    }

    throw new RuntimeException("No row labelled {$label}");
}

it('exports the trial balance with the same totals as the screen in CSV, Excel and PDF', function () {
    $query = 'from=2026-01-01&to=2026-01-31';
    $screen = $this->getJson("/api/v1/reports/trial-balance?{$query}")->assertOk()->json('data');

    $csv = csvRows($this->get("/api/v1/reports/trial-balance/export?{$query}&format=csv")->assertOk());
    expect($csv[0])->toBe(['Code', 'Account', 'Type', 'Debit', 'Credit', 'Balance'])
        ->and((float) rowValue($csv, 1, 'Total', 3))->toBe((float) $screen['total_debit'])
        ->and((float) rowValue($csv, 1, 'Total', 4))->toBe((float) $screen['total_credit'])
        ->and(count($csv))->toBe(count($screen['accounts']) + 2); // header + accounts + total

    $xlsx = xlsxRows($this->get("/api/v1/reports/trial-balance/export?{$query}&format=xlsx")->assertOk());
    expect($xlsx[0][0])->toBe('Trial Balance')
        ->and(rowValue($xlsx, 1, 'Total', 3))->toEqual(14400)
        ->and(rowValue($xlsx, 1, 'Total', 4))->toEqual(14400);

    $pdf = $this->get("/api/v1/reports/trial-balance/export?{$query}&format=pdf")->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
});

it('exports profit and loss with the same net profit, and money cells are plain numbers', function () {
    $query = 'from=2026-01-01&to=2026-01-31';
    $screen = $this->getJson("/api/v1/reports/profit-and-loss?{$query}")->assertOk()->json('data');

    $csv = csvRows($this->get("/api/v1/reports/profit-and-loss/export?{$query}&format=csv")->assertOk());
    expect((float) rowValue($csv, 1, 'Net Profit', 2))->toBe((float) $screen['net_profit'])
        ->and((float) rowValue($csv, 1, 'Gross Profit', 2))->toBe((float) $screen['gross_profit'])
        ->and(rowValue($csv, 1, 'Total Revenue', 2))->toBe('3000'); // no symbol, no thousands separator

    $xlsx = xlsxRows($this->get("/api/v1/reports/profit-and-loss/export?{$query}&format=xlsx")->assertOk());
    $net = rowValue($xlsx, 1, 'Net Profit', 2);
    expect($net)->toBeNumeric()->and((float) $net)->toBe(1000.0);
});

it('exports the balance sheet as at a date, balanced like the screen', function () {
    $screen = $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-01-31')->assertOk()->json('data');

    $csv = csvRows($this->get('/api/v1/reports/balance-sheet/export?as_of=2026-01-31&format=csv')->assertOk());
    expect((float) rowValue($csv, 1, 'Total Assets', 2))->toBe((float) $screen['assets']['total'])
        ->and((float) rowValue($csv, 1, 'Total Liabilities + Equity', 2))->toBe((float) $screen['total_liabilities_and_equity'])
        ->and((float) rowValue($csv, 1, 'Net Income (accumulated)', 2))->toBe((float) $screen['equity']['net_income']);

    $this->get('/api/v1/reports/balance-sheet/export?as_of=2026-01-31&format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('exports the general ledger with an opening and closing row per account', function () {
    $query = 'from=2026-01-01&to=2026-01-31';
    $screen = $this->getJson("/api/v1/reports/general-ledger?{$query}")->assertOk()->json('data');
    $csv = csvRows($this->get("/api/v1/reports/general-ledger/export?{$query}&format=csv")->assertOk());

    $lines = collect($screen['accounts'])->sum(fn ($ledger) => count($ledger['lines']));
    // header + per account (section, opening, closing) + every line
    expect(count($csv))->toBe(1 + count($screen['accounts']) * 3 + $lines);

    $cash = collect($screen['accounts'])->firstWhere('account.code', '1000');
    $closing = collect($csv)->first(fn ($row) => $row[1] === 'Closing Balance' && (float) $row[7] === (float) $cash['closing_balance']);
    expect($closing)->not->toBeNull();
});

it('applies the same SOF filter to the export as to the screen', function () {
    $donor = Donor::create(['company_id' => $this->company->id, 'name' => 'Test Donor']);
    $grant = Grant::create(['company_id' => $this->company->id, 'donor_id' => $donor->id, 'code' => 'SOF-9', 'name' => 'Nine', 'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);

    $query = "from=2026-01-01&to=2026-01-31&grant_id={$grant->id}";
    $screen = $this->getJson("/api/v1/reports/profit-and-loss?{$query}")->assertOk()->json('data');
    $xlsx = xlsxRows($this->get("/api/v1/reports/profit-and-loss/export?{$query}&format=xlsx")->assertOk());

    expect((float) rowValue($xlsx, 1, 'Net Profit', 2))->toBe((float) $screen['net_profit'])
        ->and((float) $screen['net_profit'])->toBe(0.0) // nothing in the fixture is coded to this SOF
        ->and(collect($xlsx)->pluck(0)->contains('SOF: SOF-9'))->toBeTrue(); // filter printed in the heading block
});

it('exports a Reports Center report (the transaction listing) with its totals row', function () {
    $screen = $this->getJson('/api/v1/reports/center/expense-accounts?from=2026-01-01&to=2026-01-31')->assertOk()->json('data');
    $csv = csvRows($this->get('/api/v1/reports/center/expense-accounts/export?from=2026-01-01&to=2026-01-31&format=csv')->assertOk());

    $amountColumn = array_search('amount', array_column($screen['columns'], 'key'), true);
    expect((float) end($csv)[$amountColumn])->toBe((float) $screen['totals']['amount']);

    $this->get('/api/v1/reports/center/general-ledger/export?from=2026-01-01&to=2026-01-31&format=xlsx')->assertOk();
    $this->get('/api/v1/reports/center/not-a-report/export?format=csv')->assertNotFound();
});

it('exports budget vs actual with the same totals as the screen', function () {
    $period = BudgetPeriod::create(['company_id' => $this->company->id, 'name' => 'FY26', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $period->lines()->create(['account_id' => $this->accounts['5000']->id, 'budgeted_amount' => 5000]);

    $screen = $this->getJson("/api/v1/budget-periods/{$period->id}/vs-actual")->assertOk()->json('data');
    $csv = csvRows($this->get("/api/v1/budget-periods/{$period->id}/vs-actual/export?format=csv")->assertOk());

    expect((float) rowValue($csv, 1, 'Total', 3))->toBe((float) $screen['totals']['budgeted_amount'])
        ->and((float) rowValue($csv, 1, 'Total', 4))->toBe((float) $screen['totals']['actual_amount']);
});

it('rejects an unknown format and keeps report permissions on exports', function () {
    $this->get('/api/v1/reports/trial-balance/export?from=2026-01-01&to=2026-01-31&format=docx')->assertStatus(422);

    $cashier = createUserWithRole('cashier', $this->company, $this->branch);
    Sanctum::actingAs($cashier, ['*']);
    $this->get('/api/v1/reports/trial-balance/export?from=2026-01-01&to=2026-01-31&format=csv')->assertForbidden();
    $this->get('/api/v1/reports/center/general-ledger/export?format=csv')->assertForbidden();
});

it('never exports another company\'s budget, nor names its records in filters', function () {
    [$other, $otherBranch] = createCompanyWithMainBranch();
    $theirPeriod = BudgetPeriod::withoutGlobalScopes()->create(['company_id' => $other->id, 'name' => 'Theirs', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $theirDonor = Donor::withoutGlobalScopes()->create(['company_id' => $other->id, 'name' => 'Their Donor']);
    $theirGrant = Grant::withoutGlobalScopes()->create(['company_id' => $other->id, 'donor_id' => $theirDonor->id, 'code' => 'SECRET-SOF', 'name' => 'Theirs', 'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);

    $this->get("/api/v1/budget-periods/{$theirPeriod->id}/vs-actual/export?format=csv")->assertNotFound();

    $xlsx = xlsxRows($this->get("/api/v1/reports/profit-and-loss/export?from=2026-01-01&to=2026-01-31&grant_id={$theirGrant->id}&format=xlsx")->assertOk());
    expect(collect($xlsx)->flatten()->contains('SOF: SECRET-SOF'))->toBeFalse();
});
