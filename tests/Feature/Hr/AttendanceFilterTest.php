<?php

use App\Models\Attendance;
use App\Models\Department;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    $this->engineering = Department::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Engineering']);
    $this->sales = Department::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Sales']);

    $this->alice = createEmployee($this->company, $this->branch, [
        'first_name' => 'Alice', 'last_name' => 'Anderson', 'employee_code' => 'EMP-ALICE',
    ]);
    $this->alice->update(['department_id' => $this->engineering->id]);

    $this->bob = createEmployee($this->company, $this->branch, [
        'first_name' => 'Bob', 'last_name' => 'Baker', 'employee_code' => 'EMP-BOB',
    ]);
    $this->bob->update(['department_id' => $this->sales->id]);

    Attendance::create(['employee_id' => $this->alice->id, 'date' => '2026-01-05', 'status' => 'present', 'clock_in' => '09:00']);
    Attendance::create(['employee_id' => $this->bob->id, 'date' => '2026-01-05', 'status' => 'absent']);

    Sanctum::actingAs($this->admin, ['*']);
});

it('searches attendance by employee name and by employee code', function () {
    $byName = $this->getJson('/api/v1/attendance?search=Alice');
    $byName->assertOk()->assertJsonCount(1, 'data');
    expect($byName->json('data.0.employee_id'))->toBe($this->alice->id);

    $byCode = $this->getJson('/api/v1/attendance?search=EMP-BOB');
    $byCode->assertOk()->assertJsonCount(1, 'data');
    expect($byCode->json('data.0.employee_id'))->toBe($this->bob->id);
});

it('filters attendance by department and by status', function () {
    $byDepartment = $this->getJson("/api/v1/attendance?department_id={$this->sales->id}");
    $byDepartment->assertOk()->assertJsonCount(1, 'data');
    expect($byDepartment->json('data.0.employee_id'))->toBe($this->bob->id);

    $byStatus = $this->getJson('/api/v1/attendance?status=absent');
    $byStatus->assertOk()->assertJsonCount(1, 'data');
    expect($byStatus->json('data.0.status'))->toBe('absent');
});
