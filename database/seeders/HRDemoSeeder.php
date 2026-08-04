<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\SalaryStructure;
use Illuminate\Database\Seeder;

class HRDemoSeeder extends Seeder
{
    protected array $structure = [
        'Finance' => ['Accountant', 'Finance Manager'],
        'Operations' => ['Operations Manager', 'Procurement Officer'],
        'Human Resources' => ['HR Officer'],
    ];

    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();

        if (! $branch) {
            return;
        }

        foreach (['Annual Leave' => 21, 'Sick Leave' => 10] as $name => $daysPerYear) {
            LeaveType::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['days_per_year' => $daysPerYear, 'is_paid' => true],
            );
        }

        $employeeNumber = 1;

        foreach ($this->structure as $departmentName => $titles) {
            $department = Department::firstOrCreate(
                ['company_id' => $company->id, 'name' => $departmentName],
                ['branch_id' => $branch->id],
            );

            foreach ($titles as $title) {
                $position = Position::firstOrCreate(
                    ['company_id' => $company->id, 'department_id' => $department->id, 'title' => $title],
                    ['min_salary' => 500000, 'max_salary' => 2000000],
                );

                $employee = Employee::firstOrCreate(
                    ['company_id' => $company->id, 'employee_code' => 'EMP-'.str_pad((string) $employeeNumber, 3, '0', STR_PAD_LEFT)],
                    [
                        'branch_id' => $branch->id,
                        'department_id' => $department->id,
                        'position_id' => $position->id,
                        'first_name' => $title,
                        'last_name' => 'Demo',
                        'hire_date' => now()->subMonths(6)->toDateString(),
                        'employment_type' => 'full_time',
                        'status' => 'active',
                    ],
                );

                SalaryStructure::firstOrCreate(
                    ['employee_id' => $employee->id, 'effective_date' => now()->subMonths(6)->toDateString()],
                    [
                        'basic_salary' => 1000000,
                        'allowances' => ['transport' => 100000, 'housing' => 150000],
                    ],
                );

                $employeeNumber++;
            }
        }
    }
}
