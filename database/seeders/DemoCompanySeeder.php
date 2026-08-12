<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Demo Company',
            'slug' => 'demo-company',
            'currency_code' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => '1 Demo Street',
            'phone' => '+10000000000',
            'is_main' => true,
            'is_active' => true,
        ]);

        $roles = [
            'super_admin',
            'company_admin',
            'branch_manager',
            'cashier',
            'accountant',
            'procurement_officer',
        ];

        foreach ($roles as $role) {
            $isSuperAdmin = $role === 'super_admin';

            $user = User::create([
                'company_id' => $isSuperAdmin ? null : $company->id,
                'branch_id' => $isSuperAdmin ? null : $branch->id,
                'name' => Str::headline($role),
                'email' => "{$role}@demo.test",
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);

            $user->assignRole($role);
        }
    }
}
