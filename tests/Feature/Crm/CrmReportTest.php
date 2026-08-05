<?php

use Laravel\Sanctum\Sanctum;

/**
 * One deterministic scenario, asserted against exact expected numbers:
 *
 * Leads: 3 total — 1 open, 1 converted, 1 disqualified (conversion rate 33.33%).
 *
 * Deals (all on the same customer, one crm_service):
 *   - deal1: New stage,       value 500,  assigned to admin
 *   - deal2: Contacted stage, value 800,  assigned to staffA
 *   - deal3: Closed Won,      value 1200, assigned to admin
 *   - deal4: Closed Lost,     value 300,  assigned to staffA
 *
 * Customer services: two entries on the same customer, 100 + 200 = 300 total
 * revenue, both active (1 distinct customer with active services).
 *
 * Account assignment: admin is primary owner of the customer.
 * Activities: one open complaint, logged by admin.
 */
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->staffA = createUserWithRole('branch_manager', $this->company, $this->branch);
    $this->customer = createCustomer($this->company);
    $this->service = createCrmService($this->company);

    $this->newStage = createCrmPipelineStage($this->company, ['name' => 'New', 'position' => 1]);
    $this->contactedStage = createCrmPipelineStage($this->company, ['name' => 'Contacted', 'position' => 2]);
    $this->wonStage = createCrmPipelineStage($this->company, ['name' => 'Won', 'position' => 3, 'is_closed_won' => true]);
    $this->lostStage = createCrmPipelineStage($this->company, ['name' => 'Lost', 'position' => 4, 'is_closed_lost' => true]);

    Sanctum::actingAs($this->admin, ['*']);

    // Leads: open, converted, disqualified.
    $this->postJson('/api/v1/crm/leads', ['name' => 'Open Lead', 'assigned_to' => $this->admin->id])->assertCreated();

    $toConvert = $this->postJson('/api/v1/crm/leads', ['name' => 'Convert Me', 'phone' => '+211900000000'])->json('data');
    $this->postJson("/api/v1/crm/leads/{$toConvert['id']}/convert")->assertOk();

    $toDisqualify = $this->postJson('/api/v1/crm/leads', ['name' => 'Disqualify Me'])->json('data');
    $this->putJson("/api/v1/crm/leads/{$toDisqualify['id']}", ['status' => 'disqualified'])->assertOk();

    // Deals.
    $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->newStage->id,
        'crm_service_id' => $this->service->id,
        'title' => 'Deal 1', 'value' => 500, 'assigned_to' => $this->admin->id,
    ])->assertCreated();

    $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->contactedStage->id,
        'title' => 'Deal 2', 'value' => 800, 'assigned_to' => $this->staffA->id,
    ])->assertCreated();

    $deal3 = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->newStage->id,
        'title' => 'Deal 3', 'value' => 1200, 'assigned_to' => $this->admin->id,
    ])->json('data');
    $this->patchJson("/api/v1/crm/deals/{$deal3['id']}/stage", ['pipeline_stage_id' => $this->wonStage->id])->assertOk();

    $deal4 = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_stage_id' => $this->newStage->id,
        'title' => 'Deal 4', 'value' => 300, 'assigned_to' => $this->staffA->id,
    ])->json('data');
    $this->patchJson("/api/v1/crm/deals/{$deal4['id']}/stage", [
        'pipeline_stage_id' => $this->lostStage->id,
        'lost_reason' => 'Budget',
    ])->assertOk();

    // Customer services.
    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id, 'crm_service_id' => $this->service->id,
        'price_charged' => 100, 'start_date' => now()->subMonth()->toDateString(),
    ])->assertCreated();
    $this->postJson('/api/v1/crm/customer-services', [
        'customer_id' => $this->customer->id, 'crm_service_id' => $this->service->id,
        'price_charged' => 200, 'start_date' => now()->toDateString(),
    ])->assertCreated();

    // Account assignment.
    $this->postJson('/api/v1/crm/account-assignments', [
        'customer_id' => $this->customer->id, 'user_id' => $this->admin->id, 'role' => 'primary',
    ])->assertCreated();

    // Activities: one open complaint, logged by admin.
    $this->postJson('/api/v1/crm/activities', [
        'customer_id' => $this->customer->id, 'type' => 'complaint',
        'subject' => 'Unhappy', 'activity_date' => now()->toDateString(),
    ])->assertCreated();
});

it('computes the CRM summary report correctly against a known scenario', function () {
    $summary = $this->getJson('/api/v1/crm/reports/summary')->assertOk()->json('data');

    expect($summary['leads']['total'])->toBe(3);
    expect($summary['leads']['by_status'])->toBe(['open' => 1, 'converted' => 1, 'disqualified' => 1]);
    expect($summary['conversion_rate'])->toBe(33.33);

    $byStage = collect($summary['deals']['by_stage'])->keyBy('stage_name');
    expect($byStage['New']['count'])->toBe(1);
    expect($byStage['New']['total_value'])->toEqual(500.0);
    expect($byStage['Contacted']['count'])->toBe(1);
    expect($byStage['Contacted']['total_value'])->toEqual(800.0);
    expect($byStage['Won']['count'])->toBe(1);
    expect($byStage['Won']['total_value'])->toEqual(1200.0);
    expect($byStage['Lost']['count'])->toBe(1);
    expect($byStage['Lost']['total_value'])->toEqual(300.0);

    expect($summary['customers_with_active_services'])->toBe(1);
    expect($summary['total_revenue'])->toEqual(300.0);

    $staffPerformance = collect($summary['staff_performance'])->keyBy('user_id');
    expect($staffPerformance)->toHaveCount(1);
    expect($staffPerformance[$this->admin->id]['deals_closed_won'])->toBe(1);
    expect($staffPerformance[$this->admin->id]['total_value'])->toEqual(1200.0);
});

it('computes the per-staff CRM breakdown correctly against the same scenario', function () {
    $staff = collect($this->getJson('/api/v1/crm/reports/staff')->assertOk()->json('data'))->keyBy('user_id');

    $adminRow = $staff[$this->admin->id];
    expect($adminRow['customers_assigned'])->toBe(1);
    expect($adminRow['open_activities_count'])->toBe(1);
    expect($adminRow['open_complaints_count'])->toBe(1);
    expect(collect($adminRow['deals'])->pluck('title')->sort()->values()->all())->toBe(['Deal 1', 'Deal 3']);
    expect($adminRow['closed_won_value'])->toEqual(1200.0);

    $staffARow = $staff[$this->staffA->id];
    expect($staffARow['customers_assigned'])->toBe(0);
    expect($staffARow['open_activities_count'])->toBe(0);
    expect(collect($staffARow['deals'])->pluck('title')->sort()->values()->all())->toBe(['Deal 2', 'Deal 4']);
    expect($staffARow['closed_won_value'])->toEqual(0.0);
});
