<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Global reference data (no company_id) — safe to re-run, and safe to
     * run once in production for companies that registered before this
     * table existed.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'SSP', 'name' => 'South Sudanese Pound', 'symbol' => 'SSP'],
            ['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh'],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'TSh'],
            ['code' => 'RWF', 'name' => 'Rwandan Franc', 'symbol' => 'FRw'],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
