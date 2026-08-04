<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Codes 1000, 1200, 1900, 2000, 2100, 2200, 2300, 5000, 5100, 5300 and
     * 5400 are relied on by code in PayrollRunService, DepreciationService,
     * the procurement services (GrnService, SupplierPaymentService), and the
     * POS services (SaleService, CashDrawerService) when posting journal
     * entries.
     */
    protected array $accounts = [
        ['code' => '1000', 'name' => 'Cash and Bank', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
        ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset'],
        ['code' => '1900', 'name' => 'Accumulated Depreciation', 'type' => 'asset'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
        ['code' => '2100', 'name' => 'Statutory Deductions Payable', 'type' => 'liability'],
        ['code' => '2200', 'name' => 'Net Salaries Payable', 'type' => 'liability'],
        ['code' => '2300', 'name' => 'Tax Payable', 'type' => 'liability'],
        ['code' => '3000', 'name' => "Owner's Equity", 'type' => 'equity'],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue'],
        ['code' => '5000', 'name' => 'Salaries Expense', 'type' => 'expense'],
        ['code' => '5100', 'name' => 'Depreciation Expense', 'type' => 'expense'],
        ['code' => '5200', 'name' => 'General Operating Expenses', 'type' => 'expense'],
        ['code' => '5300', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
        ['code' => '5400', 'name' => 'Cash Short/Over', 'type' => 'expense'],
    ];

    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        foreach ($this->accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type'], 'is_active' => true],
            );
        }
    }
}
