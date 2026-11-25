<?php

use App\Models\CostCentre;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/*
 * A company on the standard reporting mode must get exactly the statements it
 * got before the not-for-profit presentation existed: the Profit & Loss and
 * Balance Sheet JSON, their CSV exports, a dimension-filtered P&L, a saved
 * snapshot and the dashboard summary are compared byte for byte with a
 * snapshot recorded on the code as it was before
 * (tests/Feature/Reporting/snapshots/standard_statements.json).
 * Run with WRITE_STATEMENT_SNAPSHOT=1 to record it again.
 */

const STATEMENT_SNAPSHOT = __DIR__.'/snapshots/standard_statements.json';

afterEach(fn () => Carbon::setTestNow());

it('renders every statement of a standard company exactly as before', function () {
    Carbon::setTestNow('2026-03-20 10:00:00');
    [$company, $branch] = createCompanyWithMainBranch(['allow_self_approval' => true]);
    $accounts = seedChartOfAccounts($company);
    $accounts['3000'] = \App\Models\ChartOfAccount::create(['company_id' => $company->id, 'code' => '3000', 'name' => 'Capital', 'type' => 'equity', 'is_active' => true]);
    app(\App\Services\Finance\SystemAccountService::class)->ensureDefaults($company->id);
    Sanctum::actingAs(createUserWithRole('company_admin', $company, $branch), ['*']);
    $centre = CostCentre::create(['company_id' => $company->id, 'code' => 'CC-1', 'name' => 'Office']);

    $post = function (string $ref, string $date, array $lines) {
        $id = $this->postJson('/api/v1/journal-entries', ['reference_number' => $ref, 'entry_date' => $date, 'lines' => $lines])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/journal-entries/{$id}/post")->assertOk();
    };
    $a = fn (string $code) => $accounts[$code]->id;

    $post('JE-1', '2026-03-02', [['account_id' => $a('1000'), 'debit' => 5000, 'credit' => 0], ['account_id' => $a('3000'), 'debit' => 0, 'credit' => 5000]]);
    $post('JE-2', '2026-03-05', [['account_id' => $a('1100'), 'debit' => 2400, 'credit' => 0], ['account_id' => $a('4000'), 'debit' => 0, 'credit' => 2400]]);
    $post('JE-3', '2026-03-06', [['account_id' => $a('5300'), 'debit' => 900, 'credit' => 0], ['account_id' => $a('1200'), 'debit' => 0, 'credit' => 900]]);
    $post('JE-4', '2026-03-08', [
        ['account_id' => $a('5000'), 'debit' => 700, 'credit' => 0, 'cost_centre_id' => $centre->id],
        ['account_id' => $a('5200'), 'debit' => 150.55, 'credit' => 0],
        ['account_id' => $a('2000'), 'debit' => 0, 'credit' => 850.55],
    ]);
    $post('JE-5', '2026-03-10', [['account_id' => $a('1200'), 'debit' => 1200, 'credit' => 0], ['account_id' => $a('2000'), 'debit' => 0, 'credit' => 1200]]);

    $csv = fn (string $url) => $this->get($url)->assertOk()->streamedContent();
    $range = 'from=2026-03-01&to=2026-03-20';

    $actual = [
        'profit_and_loss' => $this->getJson("/api/v1/reports/profit-and-loss?{$range}")->assertOk()->json('data'),
        'profit_and_loss_cost_centre' => $this->getJson("/api/v1/reports/profit-and-loss?{$range}&cost_centre_id={$centre->id}")->assertOk()->json('data'),
        'balance_sheet' => $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-03-20')->assertOk()->json('data'),
        'profit_and_loss_csv' => $csv("/api/v1/reports/profit-and-loss/export?{$range}&format=csv"),
        'balance_sheet_csv' => $csv('/api/v1/reports/balance-sheet/export?as_of=2026-03-20&format=csv'),
        'profit_and_loss_snapshot' => $this->postJson("/api/v1/reports/profit-and-loss/snapshot?{$range}")->assertSuccessful()->json('data.data'),
        'dashboard' => $this->getJson('/api/v1/dashboard/summary')->assertOk()->json('data'),
    ];
    // Account ids differ from run to run; the codes identify each line.
    $json = json_encode(stripIds($actual), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

    if (getenv('WRITE_STATEMENT_SNAPSHOT')) {
        @mkdir(dirname(STATEMENT_SNAPSHOT), 0777, true);
        file_put_contents(STATEMENT_SNAPSHOT, $json."\n");
    }

    expect($json."\n")->toBe(file_get_contents(STATEMENT_SNAPSHOT));
});

function stripIds(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    $out = [];
    foreach ($value as $key => $item) {
        if (in_array($key, ['account_id', 'id', 'company_id', 'created_by', 'created_at', 'updated_at'], true)) {
            continue;
        }
        $out[$key] = stripIds($item);
    }

    return $out;
}
