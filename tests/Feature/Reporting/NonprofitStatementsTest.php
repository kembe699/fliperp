<?php

use App\Models\ChartOfAccount;
use App\Services\Finance\ChartReplacementService;
use App\Services\Finance\SystemAccountService;
use App\Models\CostCentre;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/*
 * A not-for-profit company (reporting_mode 'nonprofit'), on the real G711
 * chart, sees a Statement of Income and Expenses and a Statement of Financial
 * Position: not-for-profit labels, no cost-of-sales block, accounts grouped
 * under the chart's headings — with the same figures as the standard reports.
 */

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    Carbon::setTestNow('2026-03-20 10:00:00');
    [$this->company, $branch] = createCompanyWithMainBranch(['allow_self_approval' => true]);
    seedChartOfAccounts($this->company);
    app(SystemAccountService::class)->ensureDefaults($this->company->id);
    $chart = json_decode(file_get_contents(base_path('database/data/g711_chart_of_accounts.json')), true);
    app(ChartReplacementService::class)->replace($this->company, $chart, apply: true);
    $this->company->update(['reporting_mode' => 'nonprofit']);
    Sanctum::actingAs(createUserWithRole('company_admin', $this->company, $branch), ['*']);
    $this->account = fn (string $code) => ChartOfAccount::where('company_id', $this->company->id)->where('code', $code)->value('id');
});

function postEntry($test, string $ref, string $date, array $lines): void
{
    $lines = array_map(fn ($l) => ['account_id' => ($test->account)($l[0]), 'debit' => $l[1], 'credit' => $l[2]] + ($l[3] ?? []), $lines);
    $id = $test->postJson('/api/v1/journal-entries', ['reference_number' => $ref, 'entry_date' => $date, 'lines' => $lines])->assertCreated()->json('data.id');
    $test->postJson("/api/v1/journal-entries/{$id}/post")->assertOk();
}

function flatten(array $groups): array
{
    $out = [];
    foreach ($groups as $g) {
        $out[$g['code']] = $g['total'];
        $out += flatten($g['groups']);
    }

    return $out;
}

it('presents the P&L as a Statement of Income and Expenses grouped by heading, with no cost of sales', function () {
    $centre = CostCentre::create(['company_id' => $this->company->id, 'code' => 'CC-1', 'name' => 'Wau']);
    postEntry($this, 'JE-1', '2026-03-02', [['1121', 10000, 0], ['4110', 0, 10000]]);
    postEntry($this, 'JE-2', '2026-03-03', [['5390', 1200, 0, ['cost_centre_id' => $centre->id]], ['6810', 300, 0], ['6110', 2000, 0], ['7210', 50, 0], ['1121', 0, 3550]]);

    $data = $this->getJson('/api/v1/reports/profit-and-loss?from=2026-03-01&to=2026-03-20')->assertOk()->json('data');
    $p = $data['presentation'];

    expect($p['title'])->toBe('Statement of Income and Expenses')
        ->and($p['income']['label'])->toBe('Income')
        ->and($p['expenditure']['label'])->toBe('Expenditure')
        ->and($p['result_label'])->toBe('Surplus/(Deficit)')
        ->and($p['show_cost_of_sales'])->toBeFalse()
        ->and($p)->not->toHaveKey('cost_of_sales')
        // 5390 is ordinary (programme) expenditure, not cost of sales.
        ->and($data['cogs']['accounts'])->toBe([])
        ->and($data['net_profit'])->toEqual(6450)
        ->and($p['expenditure']['total'])->toEqual(3550);

    $top = collect($p['expenditure']['groups'])->pluck('total', 'code');
    expect($top->keys()->map(fn ($k) => (string) $k)->all())->toBe(['5000', '6000', '7000', '8000'])
        ->and($top['5000'])->toEqual(1200)
        ->and($top['6000'])->toEqual(2300)
        ->and($top['7000'])->toEqual(50)
        ->and($top['8000'])->toEqual(0);
    // Fundraising (6800) is its own subtotal inside Management and Support.
    expect(flatten($p['expenditure']['groups'])['6800'])->toEqual(300);

    // Filters still apply: only the cost-centre-coded 5390 line.
    $filtered = $this->getJson("/api/v1/reports/profit-and-loss?from=2026-03-01&to=2026-03-20&cost_centre_id={$centre->id}")->assertOk()->json('data');
    expect($filtered['presentation']['expenditure']['total'])->toEqual(1200)
        ->and($filtered['presentation']['income']['total'])->toEqual(0);

    $csv = $this->get('/api/v1/reports/profit-and-loss/export?from=2026-03-01&to=2026-03-20&format=csv')->assertOk()->streamedContent();
    expect($csv)->toContain('"Total Expenditure",3550')->toContain(',Surplus/(Deficit),6450')
        ->not->toContain('Gross Profit')->not->toContain('Cost of Goods Sold');
});

it('can turn cost of sales back on for a not-for-profit company', function () {
    $this->company->update(['reporting_options' => ['show_cost_of_sales' => true]]);
    postEntry($this, 'JE-1', '2026-03-03', [['5390', 1200, 0], ['1121', 0, 1200]]);

    $p = $this->getJson('/api/v1/reports/profit-and-loss?from=2026-03-01&to=2026-03-20')->assertOk()->json('data.presentation');

    expect($p['show_cost_of_sales'])->toBeTrue()
        ->and($p['cost_of_sales']['total'])->toEqual(1200)
        ->and($p['expenditure']['total'])->toEqual(0);
});

it('presents the Balance Sheet as a Statement of Financial Position with Net Assets / Funds', function () {
    postEntry($this, 'JE-1', '2026-03-02', [['1121', 10000, 0], ['2210', 0, 4000], ['3100', 0, 6000]]);
    postEntry($this, 'JE-2', '2026-03-03', [['6110', 1000, 0], ['1121', 0, 1000]]);

    $data = $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-03-20')->assertOk()->json('data');
    $p = $data['presentation'];

    expect($p['title'])->toBe('Statement of Financial Position')
        ->and($p['net_assets']['label'])->toBe('Net Assets / Funds')
        ->and($p['surplus_label'])->toBe('Current year surplus/(deficit)')
        ->and(collect($p['assets']['groups'])->pluck('code')->all())->toBe(['1000'])
        ->and(flatten($p['assets']['groups'])['1100'])->toEqual(9000)
        ->and(flatten($p['liabilities']['groups'])['2200'])->toEqual(4000)
        ->and($data['equity']['net_income'])->toEqual(-1000)
        ->and($data['assets']['total'])->toEqual($data['total_liabilities_and_equity']);

    // Every active fund account is listed, with or without a balance.
    $funds = collect($p['net_assets']['groups'][0]['accounts'])->pluck('amount', 'code');
    expect($funds->keys()->map(fn ($k) => (string) $k)->all())->toBe(['3100', '3200', '3300', '3400', '3500', '3600', '3700'])
        ->and($funds['3100'])->toEqual(6000);

    $csv = $this->get('/api/v1/reports/balance-sheet/export?as_of=2026-03-20&format=csv')->assertOk()->streamedContent();
    expect($csv)->toContain('"Net Assets / Funds"')->toContain('"Current year surplus/(deficit)",-1000')
        ->toContain('"Total Liabilities and Net Assets",9000')->not->toContain('Net Income');
});

it('shows the surplus/(deficit) on the dashboard of a not-for-profit company', function () {
    Carbon::setTestNow('2026-03-20 10:00:00');
    postEntry($this, 'JE-1', '2026-03-02', [['1121', 5000, 0], ['4110', 0, 5000]]);
    postEntry($this, 'JE-2', '2026-03-03', [['5390', 1200, 0], ['6110', 800, 0], ['1121', 0, 2000]]);

    expect($this->getJson('/api/v1/dashboard/summary')->assertOk()->json('data.gross_profit_estimate'))->toEqual(3000);
    expect($this->getJson('/api/v1/auth/me')->json('data.company.reporting_mode'))->toBe('nonprofit');
});

it('writes a sample of both G711 statements', function () {
    $pl = $this->getJson('/api/v1/reports/profit-and-loss?from=2026-03-01&to=2026-03-20')->json('data');
    $out = getenv('STATEMENT_SAMPLE_DIR');
    if ($out) {
        file_put_contents("{$out}/income_and_expenses.csv", $this->get('/api/v1/reports/profit-and-loss/export?from=2026-03-01&to=2026-03-20&format=csv')->streamedContent());
        file_put_contents("{$out}/financial_position.csv", $this->get('/api/v1/reports/balance-sheet/export?as_of=2026-03-20&format=csv')->streamedContent());
        file_put_contents("{$out}/income_and_expenses.pdf", $this->get('/api/v1/reports/profit-and-loss/export?from=2026-03-01&to=2026-03-20&format=pdf')->getContent());
        file_put_contents("{$out}/financial_position.pdf", $this->get('/api/v1/reports/balance-sheet/export?as_of=2026-03-20&format=pdf')->getContent());
    }
    expect($pl['presentation']['title'])->toBe('Statement of Income and Expenses');
});
