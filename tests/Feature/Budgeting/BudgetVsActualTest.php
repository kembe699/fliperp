<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('compares budgeted amounts against actual posted journal activity', function () {
    $budgetPeriodId = $this->postJson('/api/v1/budget-periods', [
        'name' => 'FY2026 Q1',
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
        'status' => 'active',
    ])->json('data.id');

    $this->postJson('/api/v1/budget-lines', [
        'budget_period_id' => $budgetPeriodId,
        'account_id' => $this->accounts['5200']->id,
        'budgeted_amount' => 1000,
    ])->assertCreated();

    foreach ([300, 400] as $amount) {
        $entryId = $this->postJson('/api/v1/journal-entries', [
            'reference_number' => 'EXP-'.$amount,
            'entry_date' => '2026-02-15',
            'lines' => [
                ['account_id' => $this->accounts['5200']->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->accounts['1000']->id, 'debit' => 0, 'credit' => $amount],
            ],
        ])->json('data.id');

        $this->postJson("/api/v1/journal-entries/{$entryId}/post")->assertOk();
    }

    // An unposted draft entry must not count towards actuals.
    $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'EXP-DRAFT',
        'entry_date' => '2026-02-20',
        'lines' => [
            ['account_id' => $this->accounts['5200']->id, 'debit' => 999, 'credit' => 0],
            ['account_id' => $this->accounts['1000']->id, 'debit' => 0, 'credit' => 999],
        ],
    ])->assertCreated();

    $response = $this->getJson("/api/v1/budget-periods/{$budgetPeriodId}/vs-actual");

    $response->assertOk();

    $line = collect($response->json('data'))->firstWhere('account_id', $this->accounts['5200']->id);

    expect((float) $line['budgeted_amount'])->toBe(1000.0);
    expect((float) $line['actual_amount'])->toBe(700.0);
    expect((float) $line['variance'])->toBe(300.0);
    expect((float) $line['utilization_percent'])->toBe(70.0);
});
