<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds Nile Hive Concept Co. Ltd as the platform company (is_platform = true) — the
 * tenant that owns every client's billing customer record and whose Accounting Reports
 * ARE the platform's own P&L/Trial Balance.
 *
 * Deliberately NOT the existing "Demo Company" seeded by DemoCompanySeeder: that row is
 * disposable sample/trial data (its own users are literally {role}@demo.test / password,
 * meant for kicking the tyres of the product) — reusing it as the real platform-operator
 * identity would mean the operator's own books live inside what's conceptually a demo
 * sandbox. A company is_platform-flagged is meant to be a stable, real identity, so it
 * gets its own row.
 */
class PlatformCompanySeeder extends Seeder
{
    public function run(): void
    {
        if (Company::where('is_platform', true)->exists()) {
            return;
        }

        $company = Company::create([
            'name' => 'Nile Hive Concept Co. Ltd',
            'slug' => 'nile-hive-concept',
            'currency_code' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
            'status' => 'active',
            'is_platform' => true,
        ]);

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Head Office',
            'code' => 'HQ',
            'is_main' => true,
            'is_active' => true,
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@nilehc.tech',
            'password' => Hash::make('PlatformAdmin123!'),
            'is_active' => true,
            'is_platform_staff' => true,
        ]);
        $admin->assignRole('platform_admin');
    }
}
