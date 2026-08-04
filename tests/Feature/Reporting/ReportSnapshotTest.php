<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);
});

it('persists a trial balance snapshot with the exact generated data and retrieves it later', function () {
    $response = $this->postJson('/api/v1/reports/trial-balance/snapshot?from=2026-01-01&to=2026-01-31')
        ->assertCreated();

    expect($response->json('data.report_type'))->toBe('trial-balance');
    expect((float) $response->json('data.data.total_debit'))->toBe(14400.0);
    expect((float) $response->json('data.data.total_credit'))->toBe(14400.0);

    $snapshotId = $response->json('data.id');
    $this->assertDatabaseHas('report_snapshots', [
        'id' => $snapshotId,
        'report_type' => 'trial-balance',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
    ]);

    $list = $this->getJson('/api/v1/reports/snapshots?report_type=trial-balance')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->toContain($snapshotId);
});

it('persists a profit and loss snapshot matching a fresh generation', function () {
    $fresh = $this->getJson('/api/v1/reports/profit-and-loss?from=2026-01-01&to=2026-01-31')->assertOk()->json('data');

    $snapshot = $this->postJson('/api/v1/reports/profit-and-loss/snapshot?from=2026-01-01&to=2026-01-31')
        ->assertCreated()
        ->json('data');

    expect($snapshot['data'])->toBe($fresh);
});

it('rejects an unknown report type on the snapshot endpoint', function () {
    $this->postJson('/api/v1/reports/not-a-real-report/snapshot?from=2026-01-01&to=2026-01-31')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('filters saved snapshots by date range', function () {
    $this->postJson('/api/v1/reports/trial-balance/snapshot?from=2026-01-01&to=2026-01-31')->assertCreated();

    $inRange = $this->getJson('/api/v1/reports/snapshots?from=2026-01-01&to=2026-01-31')->assertOk();
    expect($inRange->json('data'))->toHaveCount(1);

    $outOfRange = $this->getJson('/api/v1/reports/snapshots?from=2026-03-01&to=2026-03-31')->assertOk();
    expect($outOfRange->json('data'))->toHaveCount(0);
});
