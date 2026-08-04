<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

it('computes a correct running balance for the Cash account, including a negative intermediate balance', function () {
    $accountId = $this->accounts['1000']->id;

    $response = $this->getJson("/api/v1/reports/general-ledger?account_id={$accountId}&from=2026-01-01&to=2026-01-31")->assertOk();

    $data = $response->json('data');

    expect((float) $data['opening_balance'])->toBe(0.0);
    expect(count($data['lines']))->toBe(3);

    expect((float) $data['lines'][0]['debit'])->toBe(2200.0);
    expect((float) $data['lines'][0]['balance'])->toBe(2200.0);

    expect((float) $data['lines'][1]['credit'])->toBe(3000.0);
    expect((float) $data['lines'][1]['balance'])->toBe(-800.0);

    expect((float) $data['lines'][2]['debit'])->toBe(1100.0);
    expect((float) $data['lines'][2]['balance'])->toBe(300.0);

    expect((float) $data['closing_balance'])->toBe(300.0);
});

it('traces general ledger lines back to their originating sale, GRN, bill and invoice via source_module/source_id', function () {
    $accountId = $this->accounts['1000']->id;

    $response = $this->getJson("/api/v1/reports/general-ledger?account_id={$accountId}&from=2026-01-01&to=2026-01-31")->assertOk();
    $lines = $response->json('data.lines');

    expect($lines[0]['source_module'])->toBe('pos');
    expect($lines[0]['source_id'])->toBe($this->fixture['sale']->id);

    expect($lines[1]['source_module'])->toBe('procurement');
    expect($lines[1]['source_id'])->toBe($this->fixture['bill']->id);

    expect($lines[2]['source_module'])->toBe('sales');
    expect($lines[2]['source_id'])->toBe($this->fixture['invoice']->id);

    $inventoryAccountId = $this->accounts['1200']->id;
    $inventoryResponse = $this->getJson("/api/v1/reports/general-ledger?account_id={$inventoryAccountId}&from=2026-01-01&to=2026-01-31")->assertOk();
    $inventoryLines = $inventoryResponse->json('data.lines');

    expect($inventoryLines[0]['source_module'])->toBe('procurement');
    expect($inventoryLines[0]['source_id'])->toBe($this->fixture['grn']->id);
    expect($inventoryLines[1]['source_module'])->toBe('pos');
    expect($inventoryLines[1]['source_id'])->toBe($this->fixture['sale']->id);
});

it('computes the correct opening balance from activity before the requested range', function () {
    $accountId = $this->accounts['1000']->id;

    $response = $this->getJson("/api/v1/reports/general-ledger?account_id={$accountId}&from=2026-01-16&to=2026-01-31")->assertOk();
    $data = $response->json('data');

    expect((float) $data['opening_balance'])->toBe(-800.0); // 2200 in - 3000 out before 01-16
    expect(count($data['lines']))->toBe(1);
    expect((float) $data['closing_balance'])->toBe(300.0);
});
