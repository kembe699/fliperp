<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    $this->stageA = createCrmPipelineStage($this->company, ['name' => 'A', 'position' => 1]);
    $this->stageB = createCrmPipelineStage($this->company, ['name' => 'B', 'position' => 2]);
    $this->stageC = createCrmPipelineStage($this->company, ['name' => 'C', 'position' => 3]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('reorders pipeline stages to match the given order', function () {
    $response = $this->postJson('/api/v1/crm/pipeline-stages/reorder', [
        'stage_ids' => [$this->stageC->id, $this->stageA->id, $this->stageB->id],
    ])->assertOk();

    $ordered = collect($response->json('data'));
    expect($ordered->pluck('id')->all())->toBe([$this->stageC->id, $this->stageA->id, $this->stageB->id]);
    expect($ordered->pluck('position')->all())->toBe([1, 2, 3]);

    expect($this->stageC->fresh()->position)->toBe(1);
    expect($this->stageA->fresh()->position)->toBe(2);
    expect($this->stageB->fresh()->position)->toBe(3);
});

it('rejects reordering with a stage id from another company', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $otherStage = createCrmPipelineStage($otherCompany);

    $this->postJson('/api/v1/crm/pipeline-stages/reorder', [
        'stage_ids' => [$this->stageA->id, $otherStage->id],
    ])->assertStatus(422);
});
