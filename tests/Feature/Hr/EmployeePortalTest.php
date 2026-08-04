<?php

use App\Models\AttendanceGeofence;
use App\Models\LeaveType;
use App\Services\EmployeePortal\LeaveSummaryService;
use App\Services\Hr\AttendanceQrTokenService;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\LeaveRequestService;

/**
 * Every HTTP call in this file authenticates as a single identity per test
 * (the portal employee, via a real bearer token from a real login) —
 * anything the *admin* side needs to set up (portal account, QR token,
 * leave requests) is created through direct service calls instead of an
 * admin-authenticated HTTP request. Laravel's test HTTP client reuses one
 * booted app container across every call within a test, and the
 * auth/Sanctum guard caches whichever user it resolves first — issuing a
 * second HTTP call as a *different* identity later in the same test just
 * silently keeps resolving to the first one. That's a test-harness quirk
 * (a real browser never shares a guard instance across two different
 * users), not a bug in the app, so the fix is to avoid needing two
 * identities' worth of real HTTP auth in one test rather than to chase it
 * in application code.
 */
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->employee = createEmployee($this->company, $this->branch, ['email' => 'portal.employee@example.test']);

    $portalResult = app(EmployeeService::class)->createPortalAccount($this->employee);
    $this->temporaryPassword = $portalResult['temporary_password'];

    AttendanceGeofence::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'name' => 'HQ',
        'latitude' => 0.3476,
        'longitude' => 32.5825,
        'radius_meters' => 100,
        'is_active' => true,
    ]);

    $this->qrToken = app(AttendanceQrTokenService::class)->regenerate($this->branch)->token;

    $this->portalToken = $this->postJson('/api/v1/employee-portal/login', [
        'email' => 'portal.employee@example.test',
        'password' => $this->temporaryPassword,
    ])->json('data.token');

    $this->asPortalEmployee = fn () => $this->withHeader('Authorization', "Bearer {$this->portalToken}");
});

it('creates a portal account with a temporary password and an employee role', function () {
    expect($this->temporaryPassword)->not->toBeEmpty();

    $employeeRecord = App\Models\Employee::find($this->employee->id);
    expect($employeeRecord->user_id)->not->toBeNull();

    $user = App\Models\User::find($employeeRecord->user_id);
    expect($user->hasRole('employee'))->toBeTrue();
});

it('rejects a portal login for the wrong password and for a non-employee account', function () {
    $this->postJson('/api/v1/employee-portal/login', [
        'email' => 'portal.employee@example.test',
        'password' => 'wrong-password',
    ])->assertStatus(422);

    // company_admin has no employee role, so portal login must reject it
    // even with the right credentials.
    $this->postJson('/api/v1/employee-portal/login', [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertStatus(422);
});

it('logs into the portal and fetches its own profile via /me using a real bearer token', function () {
    expect($this->portalToken)->not->toBeEmpty();

    $me = ($this->asPortalEmployee)()->getJson('/api/v1/employee-portal/me');
    $me->assertOk()->assertJsonPath('data.employee.id', $this->employee->id);
});

it('blocks a portal account from reaching the main HR API', function () {
    // The employee role has zero Spatie permissions, so the main employees
    // list (a different concern from the portal's own /me) must be denied.
    ($this->asPortalEmployee)()->getJson('/api/v1/employees')->assertStatus(403);
});

it('accepts clock-in within the geofence radius and rejects it outside the radius', function () {
    // ~500m away: outside the 100m radius.
    $farResponse = ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-in', [
        'branch_id' => $this->branch->id,
        'token' => $this->qrToken,
        'latitude' => 0.3521,
        'longitude' => 32.5825,
    ]);
    $farResponse->assertStatus(422);
    expect($farResponse->json('errors.location.0'))->toContain('within the office location');
    $this->assertDatabaseMissing('attendance', ['employee_id' => $this->employee->id]);

    // Exact office coordinates: within the 100m radius.
    $nearResponse = ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-in', [
        'branch_id' => $this->branch->id,
        'token' => $this->qrToken,
        'latitude' => 0.3476,
        'longitude' => 32.5825,
    ]);
    $nearResponse->assertOk();
    expect($nearResponse->json('data.clock_in'))->not->toBeNull();
    $this->assertDatabaseHas('attendance', ['employee_id' => $this->employee->id, 'status' => 'present']);
});

it('rejects clock-in with an invalid or stale QR token even from within the geofence', function () {
    $response = ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-in', [
        'branch_id' => $this->branch->id,
        'token' => 'not-a-real-token',
        'latitude' => 0.3476,
        'longitude' => 32.5825,
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.token.0'))->toContain('Invalid QR code');
    $this->assertDatabaseMissing('attendance', ['employee_id' => $this->employee->id]);
});

it('clocks out only after clocking in, and rejects a duplicate clock-out', function () {
    $payload = ['branch_id' => $this->branch->id, 'token' => $this->qrToken, 'latitude' => 0.3476, 'longitude' => 32.5825];

    // Cannot clock out before clocking in.
    ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-out', $payload)->assertStatus(422);

    ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-in', $payload)->assertOk();
    ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-out', $payload)->assertOk();

    // Duplicate clock-out is rejected.
    ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/clock-out', $payload)->assertStatus(422);
});

it('computes correct entitled/taken/remaining leave summary numbers', function () {
    $leaveType = LeaveType::create([
        'company_id' => $this->company->id,
        'name' => 'Annual Leave',
        'days_per_year' => 20,
        'is_paid' => true,
    ]);

    $leaveRequestService = app(LeaveRequestService::class);

    $approved = $leaveRequestService->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfYear()->addDays(10)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(14)->toDateString(),
    ]);
    $leaveRequestService->approve($approved);

    // A pending request must not count toward "taken" days.
    $leaveRequestService->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfYear()->addDays(30)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(31)->toDateString(),
    ]);

    $summary = ($this->asPortalEmployee)()->getJson('/api/v1/employee-portal/leave-summary');

    $summary->assertOk();
    expect($summary->json('data.total_entitled_days'))->toBe(20);
    expect($summary->json('data.total_taken_days'))->toBe(5);
    expect($summary->json('data.total_remaining_days'))->toBe(15);
    expect($summary->json('data.last_approved_leave_date'))->toBe(now()->startOfYear()->addDays(14)->toDateString());

    // Cross-check against the service directly too.
    $direct = app(LeaveSummaryService::class)->summarize($this->employee->fresh());
    expect($direct['total_taken_days'])->toBe(5);
});

it('lets an employee submit their own leave request but never on behalf of another employee', function () {
    $otherEmployee = createEmployee($this->company, $this->branch, ['email' => 'other@example.test']);
    $leaveType = LeaveType::create([
        'company_id' => $this->company->id,
        'name' => 'Sick Leave',
        'days_per_year' => 10,
        'is_paid' => true,
    ]);

    // The portal request has no employee_id field at all — even attempting
    // to inject one for another employee is ignored server-side.
    $response = ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/leave-requests', [
        'employee_id' => $otherEmployee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
    ]);

    $response->assertCreated();
    expect($response->json('data.employee_id'))->toBe($this->employee->id);

    $history = ($this->asPortalEmployee)()->getJson('/api/v1/employee-portal/leave-requests');
    $history->assertOk()->assertJsonCount(1, 'data');
    expect($history->json('data.0.employee_id'))->toBe($this->employee->id);
});

it("blocks a portal account from seeing another employee's data via the main HR endpoints", function () {
    $otherEmployee = createEmployee($this->company, $this->branch, ['email' => 'blocked-target@example.test']);

    ($this->asPortalEmployee)()
        ->getJson("/api/v1/employees/{$otherEmployee->id}")
        ->assertStatus(403);
});

it('lists the company leave types for the portal request form without granting broader HR access', function () {
    LeaveType::create(['company_id' => $this->company->id, 'name' => 'Annual Leave', 'days_per_year' => 20, 'is_paid' => true]);
    LeaveType::create(['company_id' => $this->company->id, 'name' => 'Sick Leave', 'days_per_year' => 10, 'is_paid' => true]);

    $response = ($this->asPortalEmployee)()->getJson('/api/v1/employee-portal/leave-types');
    $response->assertOk()->assertJsonCount(2, 'data');

    // The main /leave-types endpoint remains denied (employee role has zero permissions).
    ($this->asPortalEmployee)()->getJson('/api/v1/leave-types')->assertStatus(403);
});

it('reports whether a point is within range without creating an attendance record (dry run)', function () {
    $near = ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/check-location', [
        'branch_id' => $this->branch->id,
        'token' => $this->qrToken,
        'latitude' => 0.3476,
        'longitude' => 32.5825,
    ]);
    $near->assertOk()->assertJsonPath('data.within_range', true)->assertJsonPath('data.reason', null);

    $far = ($this->asPortalEmployee)()->postJson('/api/v1/employee-portal/check-location', [
        'branch_id' => $this->branch->id,
        'token' => $this->qrToken,
        'latitude' => 0.3521,
        'longitude' => 32.5825,
    ]);
    $far->assertOk()->assertJsonPath('data.within_range', false);
    expect($far->json('data.reason'))->toContain('within the office location');

    $this->assertDatabaseMissing('attendance', ['employee_id' => $this->employee->id]);
});
