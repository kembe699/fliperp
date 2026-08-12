<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountService
{
    /**
     * Codes 1000, 1100, 1200, 1900, 2000, 2100, 2200, 2300, 4000, 5000, 5100,
     * 5300 and 5400 are relied on by code in InvoiceService, PayrollRunService,
     * DepreciationService, the procurement services (GrnService,
     * SupplierPaymentService), and the POS services (SaleService,
     * CashDrawerService, CustomerPaymentService) when posting journal entries —
     * matches database/seeders/ChartOfAccountsSeeder.php (which only seeds the
     * one demo company). 3000 and 5200 aren't relied on by any service but
     * round out a minimally usable ledger.
     */
    protected const DEFAULTS = [
        ['1000', 'Cash and Bank', 'asset'],
        ['1100', 'Accounts Receivable', 'asset'],
        ['1200', 'Inventory', 'asset'],
        ['1900', 'Accumulated Depreciation', 'asset'],
        ['2000', 'Accounts Payable', 'liability'],
        ['2100', 'Statutory Deductions Payable', 'liability'],
        ['2200', 'Net Salaries Payable', 'liability'],
        ['2300', 'Tax Payable', 'liability'],
        ['3000', "Owner's Equity", 'equity'],
        ['4000', 'Sales Revenue', 'revenue'],
        ['5000', 'Salaries Expense', 'expense'],
        ['5100', 'Depreciation Expense', 'expense'],
        ['5200', 'General Operating Expenses', 'expense'],
        ['5300', 'Cost of Goods Sold', 'expense'],
        ['5400', 'Cash Short/Over', 'expense'],
    ];

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ChartOfAccount::query()->latest()->paginate($perPage);
    }

    /**
     * Inserts every default account whose code isn't already in use for this
     * company, leaving existing rows — including any the user has since
     * renamed or reclassified — untouched. Safe to call repeatedly.
     */
    public function seedDefaults(): Collection
    {
        $existingCodes = ChartOfAccount::query()->pluck('code')->all();

        foreach (self::DEFAULTS as [$code, $name, $type]) {
            if (in_array($code, $existingCodes, true)) {
                continue;
            }

            ChartOfAccount::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true]);
        }

        return ChartOfAccount::query()->orderBy('code')->get();
    }

    public function create(array $data): ChartOfAccount
    {
        return ChartOfAccount::create($data);
    }

    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        $account->update($data);

        return $account;
    }

    public function delete(ChartOfAccount $account): void
    {
        $account->delete();
    }
}
