<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
});

it('creates a balanced journal entry as draft', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-001',
        'entry_date' => now()->toDateString(),
        'description' => 'Opening balance',
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.total_debit', 500)
        ->assertJsonPath('data.total_credit', 500);

    $this->assertDatabaseHas('journal_entries', ['reference_number' => 'JE-001', 'status' => 'draft']);
});

it('rejects an unbalanced journal entry', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-002',
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 400],
        ],
    ]);

    $response->assertStatus(422)->assertJsonPath('success', false);

    $this->assertDatabaseMissing('journal_entries', ['reference_number' => 'JE-002']);
});

it('posts a draft journal entry', function () {
    $entryId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-003',
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 300, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 300],
        ],
    ])->json('data.id');

    $response = $this->postJson("/api/v1/journal-entries/{$entryId}/post");

    $response->assertOk()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.posted_by', $this->admin->id);

    $this->assertDatabaseHas('journal_entries', ['id' => $entryId, 'status' => 'posted']);
});

it('reverses a posted journal entry with swapped debit and credit lines', function () {
    $entryId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-004',
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 200],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/journal-entries/{$entryId}/post");

    $response = $this->postJson("/api/v1/journal-entries/{$entryId}/reverse");

    $response->assertCreated()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.reference_number', 'JE-004-REV')
        ->assertJsonPath('data.total_debit', 200)
        ->assertJsonPath('data.total_credit', 200);

    $this->assertDatabaseHas('journal_entries', ['id' => $entryId, 'status' => 'reversed']);

    $reversalId = $response->json('data.id');
    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $reversalId,
        'account_id' => $this->accounts['1000']->id,
        'debit' => 0,
        'credit' => 200,
    ]);
});

it('rejects reversing an entry that is still a draft', function () {
    $entryId = $this->postJson('/api/v1/journal-entries', [
        'reference_number' => 'JE-005',
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_id' => $this->accounts['1000']->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->accounts['4000']->id, 'debit' => 0, 'credit' => 100],
        ],
    ])->json('data.id');

    $this->postJson("/api/v1/journal-entries/{$entryId}/reverse")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
