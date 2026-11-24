<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\CompanySystemAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The accounts the ERP posts to by itself — cash, receivables, payables,
 * salaries, tax, revenue, cost of sales, FX and so on — per company.
 *
 * Every service that posts a journal line on its own asks account() for the
 * account behind a function instead of looking up a fixed code, so a company
 * with its own chart (G711) can point each function at its own accounts.
 *
 * A company with no mapping row for a function falls back to the account with
 * the function's default code — the codes every service used before this
 * existed, and what every company is mapped to on migration — so existing
 * postings are unchanged.
 */
class SystemAccountService
{
    /**
     * function => default code (the default chart, ChartOfAccountService::DEFAULTS),
     * label, and whether a missing default account may be created on first use
     * (as opening balances and FX differences always did).
     */
    public const FUNCTIONS = [
        'cash' => ['code' => '1000', 'label' => 'Cash / bank (payments and receipts)', 'create' => false],
        'accounts_receivable' => ['code' => '1100', 'label' => 'Accounts receivable', 'create' => false],
        'inventory' => ['code' => '1200', 'label' => 'Inventory (when a category names none)', 'create' => false],
        'accumulated_depreciation' => ['code' => '1900', 'label' => 'Accumulated depreciation', 'create' => false],
        'accounts_payable' => ['code' => '2000', 'label' => 'Accounts payable', 'create' => false],
        'statutory_deductions_payable' => ['code' => '2100', 'label' => 'Payroll deductions payable', 'create' => false],
        'net_salaries_payable' => ['code' => '2200', 'label' => 'Net salaries payable', 'create' => false],
        'tax_payable' => ['code' => '2300', 'label' => 'Tax payable (tax on invoices and sales)', 'create' => false],
        'opening_balance_equity' => ['code' => '3900', 'label' => 'Opening balance equity', 'create' => true, 'name' => 'Opening Balance Equity', 'type' => 'equity'],
        'sales_revenue' => ['code' => '4000', 'label' => 'Revenue (when a category names none)', 'create' => false],
        'salary_expense' => ['code' => '5000', 'label' => 'Salaries expense', 'create' => false],
        'depreciation_expense' => ['code' => '5100', 'label' => 'Depreciation expense', 'create' => false],
        'default_expense' => ['code' => '5200', 'label' => 'Default expense (bills and payment requests with none chosen)', 'create' => false],
        'cost_of_sales' => ['code' => '5300', 'label' => 'Cost of sales (when a category names none)', 'create' => false],
        'cash_short_over' => ['code' => '5400', 'label' => 'Cash short / over', 'create' => false],
        'fx_gain' => ['code' => '5500', 'label' => 'Realised exchange gain', 'create' => true, 'name' => 'Realised FX Gain/Loss', 'type' => 'expense'],
        'fx_loss' => ['code' => '5500', 'label' => 'Realised exchange loss', 'create' => true, 'name' => 'Realised FX Gain/Loss', 'type' => 'expense'],
    ];

    /** The account a company posts $function to. Fails clearly if it can't take a posting. */
    public function account(int $companyId, string $function): ChartOfAccount
    {
        $definition = self::FUNCTIONS[$function] ?? throw new \InvalidArgumentException("Unknown system account function [{$function}].");

        $mapped = CompanySystemAccount::query()->where('company_id', $companyId)->where('function', $function)->value('account_id');
        if ($mapped) {
            $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->find($mapped);
        } else {
            $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $definition['code'])->first();
            if (! $account && $definition['create']) {
                $account = ChartOfAccount::withoutGlobalScopes()->create([
                    'company_id' => $companyId, 'code' => $definition['code'], 'name' => $definition['name'], 'type' => $definition['type'], 'is_active' => true,
                ]);
            }
        }

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$definition['code']} is missing from the chart of accounts."],
            ]);
        }
        if (! $account->is_postable || ! $account->is_active) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["The {$definition['label']} account is set to {$account->code} {$account->name}, which ".($account->is_postable ? 'is inactive' : 'is a heading').'. Choose a postable account under Accounting > System accounts.'],
            ]);
        }

        return $account;
    }

    /** The account a function is set to, without checking it can take postings (for reports and screens). */
    public function find(int $companyId, string $function): ?ChartOfAccount
    {
        $definition = self::FUNCTIONS[$function] ?? throw new \InvalidArgumentException("Unknown system account function [{$function}].");
        $mapped = CompanySystemAccount::query()->where('company_id', $companyId)->where('function', $function)->value('account_id');

        return ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)
            ->when($mapped, fn ($q) => $q->whereKey($mapped), fn ($q) => $q->where('code', $definition['code']))
            ->first();
    }

    /** @return list<array{function: string, label: string, default_code: string, account: ?array}> */
    public function all(int $companyId): array
    {
        return collect(self::FUNCTIONS)->map(function ($definition, $function) use ($companyId) {
            $account = $this->find($companyId, $function);

            return [
                'function' => $function,
                'label' => $definition['label'],
                'default_code' => $definition['code'],
                'account' => $account ? ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type] : null,
            ];
        })->values()->all();
    }

    /**
     * Sets functions to accounts. Each must be an active, postable account of
     * the same company.
     *
     * @param  array<string, int>  $mapping  function => account id
     */
    public function update(int $companyId, array $mapping): void
    {
        $errors = [];
        foreach ($mapping as $function => $accountId) {
            if (! isset(self::FUNCTIONS[$function])) {
                $errors["accounts.{$function}"] = ['Unknown function.'];

                continue;
            }
            $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->find($accountId);
            if (! $account) {
                $errors["accounts.{$function}"] = ['Choose an account from this company\'s chart.'];
            } elseif (! $account->is_postable || ! $account->is_active) {
                $errors["accounts.{$function}"] = ["{$account->code} {$account->name} is ".($account->is_postable ? 'inactive' : 'a heading').'; choose an account that takes postings.'];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($companyId, $mapping) {
            foreach ($mapping as $function => $accountId) {
                CompanySystemAccount::query()->updateOrCreate(
                    ['company_id' => $companyId, 'function' => $function],
                    ['account_id' => $accountId],
                );
            }
        });
    }

    /** Maps every function with no row yet to the account with its default code, where that account exists. */
    public function ensureDefaults(int $companyId): void
    {
        $existing = CompanySystemAccount::query()->where('company_id', $companyId)->pluck('function')->all();
        $byCode = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->pluck('id', 'code');

        foreach (self::FUNCTIONS as $function => $definition) {
            if (! in_array($function, $existing, true) && isset($byCode[$definition['code']])) {
                CompanySystemAccount::query()->create(['company_id' => $companyId, 'function' => $function, 'account_id' => $byCode[$definition['code']]]);
            }
        }
    }
}
