<?php

use App\Models\LeaveType;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->employee = createEmployee($this->company, $this->branch);

    $this->leaveType = LeaveType::create([
        'company_id' => $this->company->id,
        'name' => 'Annual Leave',
        'days_per_year' => 10,
        'is_paid' => true,
    ]);

    Sanctum::actingAs($this->admin, ['*']);
});

it('submits and approves a leave request within balance', function () {
    $response = $this->postJson('/api/v1/leave-requests', [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-07',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.days_count', 7)
        ->assertJsonPath('data.status', 'pending');

    $leaveRequestId = $response->json('data.id');

    $this->postJson("/api/v1/leave-requests/{$leaveRequestId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by', $this->admin->id);
});

it('rejects a leave request that would exceed the remaining balance', function () {
    $firstId = $this->postJson('/api/v1/leave-requests', [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-07',
    ])->json('data.id');

    $this->postJson("/api/v1/leave-requests/{$firstId}/approve")->assertOk();

    $response = $this->postJson('/api/v1/leave-requests', [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-05',
    ]);

    $response->assertStatus(422)->assertJsonPath('success', false);
});

it('allows a rejected leave request to free up the balance it would have used', function () {
    $rejectedId = $this->postJson('/api/v1/leave-requests', [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-08',
    ])->json('data.id');

    $this->postJson("/api/v1/leave-requests/{$rejectedId}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->postJson('/api/v1/leave-requests', [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-05',
    ])->assertCreated()->assertJsonPath('data.days_count', 5);
});
