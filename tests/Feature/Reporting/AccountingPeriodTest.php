<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->fixture = seedReportingFixture($this->company, $this->branch);

    $this->januaryPeriodId = $this->postJson('/api/v1/accounting-periods', [
        'name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ])->json('data.id');
});

it('closes a period cleanly when all its entries are already posted', function () {
    $this->postJson("/api/v1/accounting-periods/{$this->januaryPeriodId}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $this->assertDatabaseHas('accounting_periods', ['id' => $this->januaryPeriodId, 'status' => 'closed']);
});

it('blocks closing a period that still has draft journal entries in range, listing them', function () {
    $februaryPeriodId = $this->postJson('/api/v1/accounting-periods', [
        'name' => 'February 2026',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
    ])->json('data.id');

    $draftId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'DRAFT-FEB-001',
        'entry_date' => '2026-02-10',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 50],
        ],
    ])->json('data.id');

    $response = $this->postJson("/api/v1/accounting-periods/{$februaryPeriodId}/close")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(collect($response->json('errors.draft_entries'))->first())->toContain('DRAFT-FEB-001');
    $this->assertDatabaseHas('accounting_periods', ['id' => $februaryPeriodId, 'status' => 'open']);

    // Posting the draft entry clears the way to close.
    $this->postJson("/api/v1/journal-entries/{$draftId}/post")->assertOk();
    $this->postJson("/api/v1/accounting-periods/{$februaryPeriodId}/close")->assertOk();
});

it('cannot close a period that overlaps an already-closed period', function () {
    $this->postJson("/api/v1/accounting-periods/{$this->januaryPeriodId}/close")->assertOk();

    $overlappingId = $this->postJson('/api/v1/accounting-periods', [
        'name' => 'Mid-Jan to Mid-Feb',
        'start_date' => '2026-01-15',
        'end_date' => '2026-02-15',
    ])->json('data.id');

    $this->postJson("/api/v1/accounting-periods/{$overlappingId}/close")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects posting a new journal entry dated within a closed period', function () {
    $this->postJson("/api/v1/accounting-periods/{$this->januaryPeriodId}/close")->assertOk();

    $draftId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'DRAFT-JAN-LATE-001',
        'entry_date' => '2026-01-20',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 10, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 10],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/journal-entries/{$draftId}/post")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects reversing an already-posted entry dated within a closed period', function () {
    $this->postJson("/api/v1/accounting-periods/{$this->januaryPeriodId}/close")->assertOk();

    $invoiceJournalEntryId = $this->fixture['invoice']->journal_entry_id;

    $this->postJson("/api/v1/journal-entries/{$invoiceJournalEntryId}/reverse")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('still allows posting and reversing entries dated outside the closed period', function () {
    $this->postJson("/api/v1/accounting-periods/{$this->januaryPeriodId}/close")->assertOk();

    $draftId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'DRAFT-FEB-OK-001',
        'entry_date' => '2026-02-05',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 10, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 10],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/journal-entries/{$draftId}/post")->assertOk();
    $this->postJson("/api/v1/journal-entries/{$draftId}/reverse")->assertCreated();
});

it('blocks editing and deleting a closed period', function () {
    $this->postJson("/api/v1/accounting-periods/{$this->januaryPeriodId}/close")->assertOk();

    $this->putJson("/api/v1/accounting-periods/{$this->januaryPeriodId}", ['name' => 'Renamed'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->deleteJson("/api/v1/accounting-periods/{$this->januaryPeriodId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
