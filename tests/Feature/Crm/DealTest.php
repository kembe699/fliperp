<?php

use App\Models\CrmDealStageHistory;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);

    $this->newStage = createCrmPipelineStage($this->company, ['name' => 'New Lead', 'position' => 1]);
    $this->contactedStage = createCrmPipelineStage($this->company, ['name' => 'Contacted', 'position' => 2]);
    $this->wonStage = createCrmPipelineStage($this->company, ['name' => 'Closed Won', 'position' => 3, 'is_closed_won' => true]);
    $this->lostStage = createCrmPipelineStage($this->company, ['name' => 'Closed Lost', 'position' => 4, 'is_closed_lost' => true]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createDeal = function (array $overrides = []) {
        return $this->postJson('/api/v1/crm/deals', array_merge([
            'customer_id' => $this->customer->id,
            'pipeline_stage_id' => $this->newStage->id,
            'title' => 'Test Deal',
            'value' => 1000,
        ], $overrides))->json('data');
    };
});

it('requires at least a lead or a customer when creating a deal', function () {
    $this->postJson('/api/v1/crm/deals', [
        'pipeline_stage_id' => $this->newStage->id,
        'title' => 'No owner',
        'value' => 100,
    ])->assertStatus(422);
});

it('records an initial stage history row on creation', function () {
    $deal = ($this->createDeal)();

    $history = CrmDealStageHistory::where('deal_id', $deal['id'])->get();
    expect($history)->toHaveCount(1);
    expect($history->first()->from_stage_id)->toBeNull();
    expect($history->first()->to_stage_id)->toBe($this->newStage->id);
});

it('records a stage history row every time a deal moves, in order', function () {
    $deal = ($this->createDeal)();

    $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", ['pipeline_stage_id' => $this->contactedStage->id])
        ->assertOk()
        ->assertJsonPath('data.pipeline_stage_id', $this->contactedStage->id);

    $history = CrmDealStageHistory::where('deal_id', $deal['id'])->orderBy('id')->get();
    expect($history)->toHaveCount(2);
    expect($history->last()->from_stage_id)->toBe($this->newStage->id);
    expect($history->last()->to_stage_id)->toBe($this->contactedStage->id);
});

it('sets closed_at when a deal enters a closed-won stage and prompts to log a customer service', function () {
    $deal = ($this->createDeal)();

    $response = $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", ['pipeline_stage_id' => $this->wonStage->id])
        ->assertOk();

    expect($response->json('data.closed_at'))->not->toBeNull();
    expect($response->json('meta.prompt_customer_service_log'))->toBeTrue();
});

it('requires a lost_reason when moving a deal into a closed-lost stage', function () {
    $deal = ($this->createDeal)();

    $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", ['pipeline_stage_id' => $this->lostStage->id])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $response = $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", [
        'pipeline_stage_id' => $this->lostStage->id,
        'lost_reason' => 'Went with a competitor',
    ])->assertOk();

    expect($response->json('data.closed_at'))->not->toBeNull();
    expect($response->json('data.lost_reason'))->toBe('Went with a competitor');
    expect($response->json('meta.prompt_customer_service_log'))->toBeFalse();
});

it('blocks editing a deal once it has closed', function () {
    $deal = ($this->createDeal)();
    $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", ['pipeline_stage_id' => $this->wonStage->id])->assertOk();

    $this->putJson("/api/v1/crm/deals/{$deal['id']}", ['title' => 'New title'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects moving a deal to a pipeline stage from another company', function () {
    [$otherCompany] = createCompanyWithMainBranch();
    $otherStage = createCrmPipelineStage($otherCompany);

    $deal = ($this->createDeal)();

    $this->patchJson("/api/v1/crm/deals/{$deal['id']}/stage", ['pipeline_stage_id' => $otherStage->id])
        ->assertStatus(422);
});

it('returns the kanban board grouped by stage in position order', function () {
    $dealA = ($this->createDeal)(['title' => 'Deal A']);
    $dealB = ($this->createDeal)(['pipeline_stage_id' => $this->contactedStage->id, 'title' => 'Deal B']);

    $columns = $this->getJson('/api/v1/crm/deals/kanban')->assertOk()->json('data');

    expect($columns)->toHaveCount(4);
    expect($columns[0]['stage']['id'])->toBe($this->newStage->id);
    expect(collect($columns[0]['deals'])->pluck('id')->all())->toBe([$dealA['id']]);
    expect(collect($columns[1]['deals'])->pluck('id')->all())->toBe([$dealB['id']]);
});
