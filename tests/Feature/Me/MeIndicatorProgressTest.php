<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);

    $this->projectId = $this->postJson('/api/v1/me-projects', [
        'name' => 'Clean Water Access',
        'start_date' => '2026-01-01',
        'status' => 'ongoing',
    ])->json('data.id');
});

it('calculates indicator progress from recorded results and aggregates activity completion on the dashboard', function () {
    $indicatorAId = $this->postJson('/api/v1/me-indicators', [
        'me_project_id' => $this->projectId,
        'name' => 'Wells constructed',
        'unit_of_measure' => 'wells',
        'target_value' => 200,
        'baseline_value' => 0,
    ])->json('data.id');

    $indicatorBId = $this->postJson('/api/v1/me-indicators', [
        'me_project_id' => $this->projectId,
        'name' => 'Households served',
        'unit_of_measure' => 'households',
        'target_value' => 60,
        'baseline_value' => 10,
    ])->json('data.id');

    $this->postJson('/api/v1/me-results', [
        'me_indicator_id' => $indicatorAId,
        'reporting_period' => '2026-Q1',
        'actual_value' => 80,
    ])->assertCreated();

    $this->postJson('/api/v1/me-results', [
        'me_indicator_id' => $indicatorBId,
        'reporting_period' => '2026-Q1',
        'actual_value' => 35,
    ])->assertCreated();

    $this->postJson('/api/v1/me-activities', [
        'me_project_id' => $this->projectId,
        'name' => 'Site survey',
        'start_date' => '2026-01-05',
        'status' => 'completed',
    ])->assertCreated();

    $this->postJson('/api/v1/me-activities', [
        'me_project_id' => $this->projectId,
        'name' => 'Drilling',
        'start_date' => '2026-02-01',
        'status' => 'in_progress',
    ])->assertCreated();

    $response = $this->getJson("/api/v1/me-projects/{$this->projectId}/dashboard");

    $response->assertOk();

    $indicators = collect($response->json('data.indicators'))->keyBy('indicator_id');

    expect((float) $indicators[$indicatorAId]['actual_value'])->toBe(80.0);
    expect((float) $indicators[$indicatorAId]['progress_percent'])->toBe(40.0);

    expect((float) $indicators[$indicatorBId]['actual_value'])->toBe(35.0);
    expect((float) $indicators[$indicatorBId]['progress_percent'])->toBe(50.0);

    $response->assertJsonPath('data.activities_summary.total', 2)
        ->assertJsonPath('data.activities_summary.completed', 1);

    expect((float) $response->json('data.activities_summary.completion_percent'))->toBe(50.0);
});

it('falls back to the baseline value when an indicator has no recorded results yet', function () {
    $indicatorId = $this->postJson('/api/v1/me-indicators', [
        'me_project_id' => $this->projectId,
        'name' => 'Trainings held',
        'target_value' => 50,
        'baseline_value' => 5,
    ])->json('data.id');

    $response = $this->getJson("/api/v1/me-projects/{$this->projectId}/dashboard");

    $indicator = collect($response->json('data.indicators'))->firstWhere('indicator_id', $indicatorId);

    expect((float) $indicator['actual_value'])->toBe(5.0);
    expect((float) $indicator['progress_percent'])->toBe(0.0);
});
