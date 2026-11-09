<?php

use App\Models\Department;
use App\Models\Position;
use Laravel\Sanctum\Sanctum;

// The employee form takes a typed position title instead of a pick-list.
beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    Sanctum::actingAs(createUserWithRole('company_admin', $this->company, $this->branch), ['*']);
    $this->department = Department::create(['company_id' => $this->company->id, 'name' => 'Finance']);
    $this->employee = fn (array $extra) => $this->postJson('/api/v1/employees', $extra + [
        'branch_id' => $this->branch->id,
        'department_id' => $this->department->id,
        'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
        'first_name' => 'Amani',
        'last_name' => 'Lado',
        'hire_date' => '2026-11-01',
        'employment_type' => 'full_time',
    ]);
});

it('creates the position from a typed title, and reuses it whatever the letter case', function () {
    $first = ($this->employee)(['position_title' => 'Accountant'])->assertCreated();
    $second = ($this->employee)(['position_title' => '  accountant '])->assertCreated();

    expect(Position::where('company_id', $this->company->id)->where('department_id', $this->department->id)->count())->toBe(1)
        ->and($first->json('data.position_id'))->toBe($second->json('data.position_id'))
        ->and(Position::find($first->json('data.position_id'))->title)->toBe('Accountant');
});

it('still accepts an existing position id, and needs one or the other', function () {
    $position = Position::create(['company_id' => $this->company->id, 'department_id' => $this->department->id, 'title' => 'Cashier']);
    ($this->employee)(['position_id' => $position->id])->assertCreated()->assertJsonPath('data.position_id', $position->id);
    ($this->employee)([])->assertStatus(422)->assertJsonValidationErrors(['position_id']);
});

it('changes an employee position by typing a new title', function () {
    $id = ($this->employee)(['position_title' => 'Accountant'])->json('data.id');

    $this->putJson("/api/v1/employees/{$id}", ['position_title' => 'Senior Accountant'])->assertOk();

    expect(Position::find(\App\Models\Employee::find($id)->position_id)->title)->toBe('Senior Accountant');
});
