<?php

use App\Models\ChartOfAccount;
use Laravel\Sanctum\Sanctum;

// Known scenario (seedReportingFixture, January 2026): one POS sale of 10 × 200
// + 10% tax (paid 2200 cash), one invoice of 5 × 200 + 10% tax (paid 1100),
// one supplier bill of 50 × 100 (3000 paid), payroll of 1000 gross.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

function center(string $report, array $params = []): array
{
    $query = http_build_query($params + ['from' => '2026-01-01', 'to' => '2026-01-31']);

    return test()->getJson("/api/v1/reports/center/{$report}?{$query}")->assertOk()->json('data');
}

it('overview matches the profit and loss and adds collections, purchases and stock', function () {
    $kpis = collect(center('overview')['kpis'])->keyBy('key');

    expect((float) $kpis['revenue']['value'])->toBe(3000.0)
        ->and((float) $kpis['pos']['value'])->toBe(2000.0)
        ->and((float) $kpis['invoiced']['value'])->toBe(1000.0)
        ->and((float) $kpis['collected']['value'])->toBe(3300.0)
        ->and((float) $kpis['purchases']['value'])->toBe(5000.0)
        ->and((float) $kpis['payable']['value'])->toBe(2000.0)
        ->and((float) $kpis['receivable']['value'])->toBe(0.0);
});

it('lists every revenue account, including ones with nothing posted, and they add up to the P&L', function () {
    ChartOfAccount::create(['company_id' => $this->company->id, 'code' => '4900', 'name' => 'Other Income', 'type' => 'revenue', 'is_active' => true]);
    $data = center('revenue-accounts');
    $pl = $this->getJson('/api/v1/reports/profit-and-loss?from=2026-01-01&to=2026-01-31')->json('data');

    $revenueAccounts = ChartOfAccount::where('company_id', $this->company->id)->where('type', 'revenue')->where('is_active', true)->count();
    expect(count($data['rows']))->toBe($revenueAccounts)
        ->and((float) $data['totals']['amount'])->toBe((float) $pl['revenue']['total'])
        ->and((float) collect($data['rows'])->firstWhere('code', '4900')['amount'])->toBe(0.0);
});

it('expense accounts split cost of sales from operating expenses', function () {
    $rows = collect(center('expense-accounts')['rows'])->keyBy('code');

    expect($rows['5300']['group'])->toBe('Cost of sales')
        ->and((float) $rows['5300']['amount'])->toBe(1000.0)
        ->and($rows['5000']['group'])->toBe('Operating expense');
});

it('revenue by product, customer and document agree, net of tax', function () {
    $byProduct = center('revenue', ['group_by' => 'product']);
    expect((float) $byProduct['totals']['revenue'])->toBe(3000.0)
        ->and((float) $byProduct['totals']['quantity'])->toBe(15.0)
        ->and((float) $byProduct['totals']['cost'])->toBe(1500.0);

    $byCustomer = center('revenue', ['group_by' => 'customer']);
    expect((float) $byCustomer['totals']['revenue'])->toBe(3000.0)
        ->and((float) $byCustomer['totals']['tax'])->toBe(300.0)
        ->and((float) $byCustomer['totals']['total'])->toBe(3300.0);

    $documents = center('revenue', ['group_by' => 'document']);
    expect(collect($documents['rows'])->pluck('source')->sort()->values()->all())->toBe(['Invoice', 'POS sale']);

    expect((float) center('revenue', ['group_by' => 'month', 'source' => 'pos'])['totals']['revenue'])->toBe(2000.0)
        ->and((float) center('revenue', ['group_by' => 'month', 'source' => 'invoice'])['totals']['revenue'])->toBe(1000.0);
});

it('collections cover POS payments and invoice payments, by method', function () {
    $data = center('collections', ['group_by' => 'method']);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['group'])->toBe('Cash')
        ->and((float) $data['totals']['amount'])->toBe(3300.0);
});

it('purchases, supplier payments, stock and payroll report the fixture', function () {
    expect((float) center('purchases', ['group_by' => 'supplier'])['totals']['balance'])->toBe(2000.0)
        ->and((float) center('supplier-payments', ['group_by' => 'supplier'])['totals']['amount'])->toBe(3000.0);

    $stock = center('stock', ['group_by' => 'product']);
    expect((float) $stock['totals']['on_hand'])->toBe(40.0) // 50 received − 10 sold (the invoice moves no stock)
        ->and((float) $stock['totals']['cost_value'])->toBe(4000.0);

    // A movement is dated when it was recorded (moved_at defaults to now), not the document date.
    $movements = center('stock-movements', ['group_by' => 'type', 'to' => now()->toDateString()]);
    expect((float) $movements['totals']['in'])->toBe(50.0)->and((float) $movements['totals']['out'])->toBe(10.0);

    $payroll = center('payroll', ['group_by' => 'employee']);
    expect((float) $payroll['totals']['gross'])->toBe(1000.0)->and((float) $payroll['totals']['net'])->toBe(900.0);
});

it('rejects an unknown report and a backwards date range', function () {
    $this->getJson('/api/v1/reports/center/nope')->assertNotFound();
    $this->getJson('/api/v1/reports/center/revenue?from=2026-02-01&to=2026-01-01')->assertStatus(422);
});

it('needs reports.view', function () {
    Sanctum::actingAs(createUserWithRole('cashier', $this->company, $this->branch), ['*']);
    $this->getJson('/api/v1/reports/center/overview')->assertForbidden();
});

it('general ledger lists each journal entry with its lines, and the lines balance', function () {
    $data = center('general-ledger');

    expect($data['rows'])->not->toBeEmpty();
    foreach ($data['rows'] as $row) {
        $lines = collect($row['lines']);
        expect($lines)->not->toBeEmpty()
            ->and(round($lines->sum('debit'), 2))->toBe(round($lines->sum('credit'), 2))
            ->and((float) $row['debit'])->toBe(round($lines->sum('debit'), 2));
    }

    // Revenue-type filter: only entries touching revenue, totals count the revenue lines.
    $revenue = center('general-ledger', ['account_type' => 'revenue']);
    expect((float) $revenue['totals']['credit'])->toBe(3000.0);
});
