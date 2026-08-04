<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\StatutoryDeductionRule;
use Illuminate\Database\Seeder;

class StatutoryDeductionSeeder extends Seeder
{
    /**
     * Starter, editable rates — not a substitute for a tax advisor. Everything
     * a payroll admin would need to tune lives in `config`, not code.
     *
     * Only Uganda's rules are active for the demo company: PayrollRunService
     * applies every *active* rule to every payslip with no country matching,
     * so leaving both countries active would double-tax. South Sudan's rules
     * are seeded inactive as a template — flip them on (and Uganda's off) for
     * a company operating there instead.
     */
    protected array $rules = [
        [
            'name' => 'Uganda PAYE',
            'type' => 'tax',
            'calculation_type' => 'bracket',
            'country_code' => 'UGA',
            'is_active' => true,
            'config' => [
                'brackets' => [
                    ['min' => 0, 'max' => 235000, 'rate' => 0],
                    ['min' => 235000, 'max' => 335000, 'rate' => 0.10],
                    ['min' => 335000, 'max' => 410000, 'rate' => 0.20],
                    ['min' => 410000, 'max' => null, 'rate' => 0.30],
                ],
            ],
        ],
        [
            'name' => 'Uganda NSSF (Employee)',
            'type' => 'pension',
            'calculation_type' => 'percentage',
            'country_code' => 'UGA',
            'is_active' => true,
            'config' => ['rate' => 0.05],
        ],
        [
            'name' => 'South Sudan PAYE',
            'type' => 'tax',
            'calculation_type' => 'percentage',
            'country_code' => 'SSD',
            'is_active' => false,
            'config' => ['rate' => 0.10],
        ],
        [
            'name' => 'South Sudan Pension (Employee)',
            'type' => 'pension',
            'calculation_type' => 'percentage',
            'country_code' => 'SSD',
            'is_active' => false,
            'config' => ['rate' => 0.08],
        ],
    ];

    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        foreach ($this->rules as $rule) {
            StatutoryDeductionRule::firstOrCreate(
                ['company_id' => $company->id, 'name' => $rule['name']],
                [
                    'type' => $rule['type'],
                    'calculation_type' => $rule['calculation_type'],
                    'config' => $rule['config'],
                    'country_code' => $rule['country_code'],
                    'is_active' => $rule['is_active'],
                ],
            );
        }
    }
}
