<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('defaults a newly created budget period to draft status in the response, not null', function () {
    // Postgres inserts only RETURNING the id, so a DB-level column default
    // never makes it back onto the in-memory model unless the service sets
    // it explicitly — this regression-tests that fix directly against the
    // create response, not a follow-up GET (which would mask the bug).
    $this->postJson('/api/v1/budget-periods', [
        'name' => 'FY2026 Q1',
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
    ])->assertCreated()->assertJsonPath('data.status', 'draft');
});

it('activates a draft budget period and later closes it', function () {
    $id = $this->postJson('/api/v1/budget-periods', [
        'name' => 'FY2026 Q2',
        'start_date' => '2026-04-01',
        'end_date' => '2026-06-30',
    ])->json('data.id');

    $this->putJson("/api/v1/budget-periods/{$id}", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->putJson("/api/v1/budget-periods/{$id}", ['status' => 'closed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});
