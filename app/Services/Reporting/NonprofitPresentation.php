<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\Company;

/**
 * The not-for-profit presentation of the financial statements, for companies
 * whose reporting_mode is 'nonprofit' (config/reporting.php). The figures are
 * the standard statements' own; this only names the sections the
 * not-for-profit way and groups each section's accounts under the chart's
 * headings, with a subtotal per heading.
 *
 * Grouping: a section (income, expenditure, assets ...) is the tree of the
 * chart's top-level headings of that account type — accounts with no parent
 * that take no postings, e.g. 5000 PROGRAMME EXPENSES and 6000 MANAGEMENT AND
 * SUPPORT EXPENSES. Under each heading come its sub-headings (6800
 * Fundraising and Communications) and its accounts. Top-level headings and
 * their direct sub-headings are always shown when they hold an active account,
 * so the structure reads the same in an empty period; deeper headings only
 * when they hold a line. An account appears where it has a line in the
 * standard statement; one that no heading of the section reaches lands in
 * "Other", so every line is shown exactly once and the section total equals
 * the standard statement's.
 *
 * Node shape: ['code', 'name', 'total', 'accounts' => [lines], 'groups' => [nodes]]
 * with lines exactly as the standard statement has them
 * (['account_id', 'code', 'name', 'amount']).
 */
class NonprofitPresentation
{
    /** @var array<int, ChartOfAccount> */
    protected array $accounts = [];

    /** @var array<int, array<int, int>> parent id => child ids, in code order */
    protected array $children = [];

    /** The company when it reports the not-for-profit way, else null. */
    public static function company(int $companyId): ?Company
    {
        $company = Company::query()->find($companyId);

        return $company?->isNonprofit() ? $company : null;
    }

    public function __construct(protected int $companyId)
    {
        $all = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('code')->get();
        foreach ($all as $account) {
            $this->accounts[$account->id] = $account;
            if ($account->parent_id) {
                $this->children[$account->parent_id][] = $account->id;
            }
        }
    }

    /**
     * Statement of Income and Expenses: the P&L's sections, grouped.
     *
     * @param  array  $pl  ProfitAndLossService::generate() output
     */
    public function incomeAndExpenses(array $pl, bool $showCostOfSales): array
    {
        $presentation = [
            'mode' => 'nonprofit',
            'title' => 'Statement of Income and Expenses',
            'show_cost_of_sales' => $showCostOfSales,
            'income' => ['label' => 'Income', 'total_label' => 'Total Income', ...$this->section('revenue', $pl['revenue']['accounts'], 'Other Income')],
        ];
        if ($showCostOfSales) {
            $presentation['cost_of_sales'] = ['label' => 'Cost of Sales', 'total_label' => 'Total Cost of Sales', ...$this->section('expense', $pl['cogs']['accounts'], 'Other Cost of Sales', skipEmpty: true)];
            $presentation['gross_label'] = 'Gross Surplus';
        }
        $presentation['expenditure'] = ['label' => 'Expenditure', 'total_label' => 'Total Expenditure', ...$this->section('expense', $pl['operating_expenses']['accounts'], 'Other Expenditure')];
        $presentation['result_label'] = 'Surplus/(Deficit)';

        return $presentation;
    }

    /**
     * Statement of Financial Position: the Balance Sheet's sections, grouped,
     * with equity as Net Assets / Funds listing every active fund account.
     *
     * @param  array  $bs  BalanceSheetService::generate() output
     */
    public function financialPosition(array $bs): array
    {
        return [
            'mode' => 'nonprofit',
            'title' => 'Statement of Financial Position',
            'assets' => ['label' => 'Assets', 'total_label' => 'Total Assets', ...$this->section('asset', $bs['assets']['accounts'], 'Other Assets')],
            'liabilities' => ['label' => 'Liabilities', 'total_label' => 'Total Liabilities', ...$this->section('liability', $bs['liabilities']['accounts'], 'Other Liabilities')],
            'net_assets' => ['label' => 'Net Assets / Funds', 'total_label' => 'Total Net Assets / Funds', ...$this->section('equity', $bs['equity']['accounts'], 'Other Funds', listEveryAccount: true)],
            'surplus_label' => 'Current year surplus/(deficit)',
            'total_label' => 'Total Liabilities and Net Assets',
        ];
    }

    /**
     * @param  array<int, array{account_id:int,code:string,name:string,amount:float}>  $lines
     * @return array{total: float, groups: array<int, array>}
     */
    protected function section(string $type, array $lines, string $otherLabel, bool $skipEmpty = false, bool $listEveryAccount = false): array
    {
        $byAccount = collect($lines)->keyBy('account_id')->all();
        $placed = [];
        $groups = [];

        foreach ($this->accounts as $account) {
            if ($account->parent_id === null && ! $account->is_postable && $account->type === $type) {
                $node = $this->node($account, $byAccount, $placed, 0, $listEveryAccount);
                if ($node && ! ($skipEmpty && $node['accounts'] === [] && $node['groups'] === [])) {
                    $groups[] = $node;
                }
            }
        }

        $unplaced = array_values(array_filter($lines, fn ($line) => ! isset($placed[$line['account_id']])));
        if ($unplaced) {
            $groups[] = ['code' => '', 'name' => $otherLabel, 'total' => round(array_sum(array_column($unplaced, 'amount')), 2), 'accounts' => $unplaced, 'groups' => []];
        }

        return ['total' => round(array_sum(array_column($lines, 'amount')), 2), 'groups' => $groups];
    }

    /** One heading with what sits under it; null when it isn't shown. */
    protected function node(ChartOfAccount $heading, array $byAccount, array &$placed, int $depth, bool $listEveryAccount): ?array
    {
        $accounts = [];
        $groups = [];

        if (isset($byAccount[$heading->id])) {
            // A postable account that also has accounts under it: its own line first.
            $accounts[] = $byAccount[$heading->id];
            $placed[$heading->id] = true;
        }

        foreach ($this->children[$heading->id] ?? [] as $childId) {
            $child = $this->accounts[$childId];
            if (! empty($this->children[$childId]) || ! $child->is_postable) {
                if ($sub = $this->node($child, $byAccount, $placed, $depth + 1, $listEveryAccount)) {
                    $groups[] = $sub;
                }
            } elseif (isset($byAccount[$childId])) {
                $accounts[] = $byAccount[$childId];
                $placed[$childId] = true;
            } elseif ($listEveryAccount && $child->is_active) {
                $accounts[] = ['account_id' => $child->id, 'code' => $child->code, 'name' => $child->name, 'amount' => 0.0];
            }
        }

        $hasLines = $accounts !== [] || $groups !== [];
        $structural = $depth <= 1 && $this->holdsActiveAccount($heading->id);
        if (! $hasLines && ! $structural) {
            return null;
        }

        $total = array_sum(array_column($accounts, 'amount')) + array_sum(array_column($groups, 'total'));

        return ['code' => $heading->code, 'name' => $heading->name, 'total' => round($total, 2), 'accounts' => $accounts, 'groups' => $groups];
    }

    protected function holdsActiveAccount(int $headingId): bool
    {
        foreach ($this->children[$headingId] ?? [] as $childId) {
            $child = $this->accounts[$childId];
            if (($child->is_postable && $child->is_active) || $this->holdsActiveAccount($childId)) {
                return true;
            }
        }

        return false;
    }
}
