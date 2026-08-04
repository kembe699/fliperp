<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            DemoCompanySeeder::class,
            ChartOfAccountsSeeder::class,
            StatutoryDeductionSeeder::class,
            HRDemoSeeder::class,
            InventoryDemoSeeder::class,
            ProcurementDemoSeeder::class,
            PosDemoSeeder::class,
            SalesDemoSeeder::class,
        ]);
    }
}
