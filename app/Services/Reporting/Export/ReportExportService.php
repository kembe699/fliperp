<?php

namespace App\Services\Reporting\Export;

use App\Exports\ReportTableExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\Grant;
use App\Models\GrantDea;
use App\Models\MeProject;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a report — exactly the array its JSON endpoint returns, after any
 * "Show in" currency conversion — into a CSV, Excel or PDF download. The
 * export never recalculates anything: it lays out the same figures the
 * screen shows, so its totals always match the screen's for the same filters.
 *
 * Every report is first reduced to one table shape:
 *   ['title', 'period', 'filters' => [label => value], 'currency',
 *    'columns' => [['key', 'label', 'type' (text|money|number|percent|date)]],
 *    'rows' => [[key => value, ..., '_style' => null|'section'|'subtotal'|'total']]]
 * and the three formats render that table.
 */
class ReportExportService
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public const MIME = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    /**
     * @param  array<string, mixed>  $filters  the request's filter values (branch_id, grant_id, ...), resolved to names here
     */
    public function download(array $table, string $format, User $user, array $filters = []): Response
    {
        $table['filters'] = array_merge($this->describeFilters($user->company_id, $filters), $table['filters'] ?? []);
        $filename = $this->filename($table, $format);

        return match ($format) {
            'csv' => $this->csv($table, $filename),
            'xlsx' => Excel::download(new ReportTableExport($table, $user), $filename, \Maatwebsite\Excel\Excel::XLSX),
            'pdf' => $this->pdf($table, $user)->download($filename),
        };
    }

    // ── Report shapes → one table ───────────────────────────────────────────

    public function trialBalance(array $data): array
    {
        $rows = array_map(fn ($a) => ['code' => $a['code'], 'name' => $a['name'], 'type' => ucfirst($a['type']), 'debit' => $a['debit'], 'credit' => $a['credit'], 'balance' => $a['balance']], $data['accounts']);
        $rows[] = ['code' => '', 'name' => 'Total', 'type' => '', 'debit' => $data['total_debit'], 'credit' => $data['total_credit'], 'balance' => null, '_style' => 'total'];

        return $this->table('Trial Balance', $this->range($data), $data, [
            $this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('type', 'Type'),
            $this->col('debit', 'Debit', 'money'), $this->col('credit', 'Credit', 'money'), $this->col('balance', 'Balance', 'money'),
        ], $rows);
    }

    public function profitAndLoss(array $data): array
    {
        if (isset($data['presentation'])) {
            return $this->incomeAndExpenses($data);
        }

        $rows = [
            ...$this->section('Revenue', $data['revenue']['accounts'], 'Total Revenue', $data['revenue']['total']),
            ...$this->section('Cost of Goods Sold', $data['cogs']['accounts'], 'Total Cost of Goods Sold', $data['cogs']['total']),
            ['code' => '', 'name' => 'Gross Profit', 'amount' => $data['gross_profit'], '_style' => 'subtotal'],
            ...$this->section('Operating Expenses', $data['operating_expenses']['accounts'], 'Total Operating Expenses', $data['operating_expenses']['total']),
            ['code' => '', 'name' => 'Net Profit', 'amount' => $data['net_profit'], '_style' => 'total'],
        ];

        return $this->table('Profit & Loss', $this->range($data), $data, $this->amountColumns(), $rows);
    }

    public function balanceSheet(array $data): array
    {
        if (isset($data['presentation'])) {
            return $this->financialPosition($data);
        }

        $rows = [
            ...$this->section('Assets', $data['assets']['accounts'], 'Total Assets', $data['assets']['total']),
            ...$this->section('Liabilities', $data['liabilities']['accounts'], 'Total Liabilities', $data['liabilities']['total']),
            ['code' => '', 'name' => 'Equity', 'amount' => null, '_style' => 'section'],
            ...$this->accountRows($data['equity']['accounts']),
            ['code' => '', 'name' => 'Net Income (accumulated)', 'amount' => $data['equity']['net_income']],
            ['code' => '', 'name' => 'Total Equity', 'amount' => $data['equity']['total'], '_style' => 'subtotal'],
            ['code' => '', 'name' => 'Total Liabilities + Equity', 'amount' => $data['total_liabilities_and_equity'], '_style' => 'total'],
        ];

        return $this->table('Balance Sheet', 'As of '.$data['as_of'], $data, $this->amountColumns(), $rows);
    }

    /** A not-for-profit company's P&L: Statement of Income and Expenses, grouped by heading. */
    protected function incomeAndExpenses(array $data): array
    {
        $p = $data['presentation'];
        $rows = $this->groupedSection($p['income']);
        if (! empty($p['show_cost_of_sales'])) {
            $rows = [...$rows, ...$this->groupedSection($p['cost_of_sales']), ['code' => '', 'name' => $p['gross_label'], 'amount' => $data['gross_profit'], '_style' => 'subtotal']];
        }
        $rows = [...$rows, ...$this->groupedSection($p['expenditure']), ['code' => '', 'name' => $p['result_label'], 'amount' => $data['net_profit'], '_style' => 'total']];

        return $this->table($p['title'], $this->range($data), $data, $this->amountColumns(), $rows);
    }

    /** A not-for-profit company's Balance Sheet: Statement of Financial Position, grouped by heading. */
    protected function financialPosition(array $data): array
    {
        $p = $data['presentation'];
        // The fund accounts' own total is replaced by one that includes the surplus line.
        $netAssets = $this->groupedSection($p['net_assets']);
        array_pop($netAssets);
        $rows = [
            ...$this->groupedSection($p['assets']),
            ...$this->groupedSection($p['liabilities']),
            ...$netAssets,
            ['code' => '', 'name' => $p['surplus_label'], 'amount' => $data['equity']['net_income']],
            ['code' => '', 'name' => $p['net_assets']['total_label'], 'amount' => $data['equity']['total'], '_style' => 'subtotal'],
            ['code' => '', 'name' => $p['total_label'], 'amount' => $data['total_liabilities_and_equity'], '_style' => 'total'],
        ];

        return $this->table($p['title'], 'As of '.$data['as_of'], $data, $this->amountColumns(), $rows);
    }

    /** A grouped section: its title, each heading with its accounts and a subtotal, then the section total. */
    protected function groupedSection(array $section): array
    {
        $rows = [['code' => '', 'name' => $section['label'], 'amount' => null, '_style' => 'section']];
        foreach ($section['groups'] as $group) {
            $rows = [...$rows, ...$this->groupRows($group, 0)];
        }
        $rows[] = ['code' => '', 'name' => $section['total_label'], 'amount' => $section['total'], '_style' => 'subtotal'];

        return $rows;
    }

    protected function groupRows(array $group, int $depth): array
    {
        $indent = str_repeat('    ', $depth);
        $rows = [['code' => $group['code'], 'name' => $indent.$group['name'], 'amount' => null, '_style' => $depth === 0 ? 'section' : null]];
        foreach ($group['accounts'] as $account) {
            $rows[] = ['code' => $account['code'], 'name' => $indent.'    '.$account['name'], 'amount' => $account['amount']];
        }
        foreach ($group['groups'] as $sub) {
            $rows = [...$rows, ...$this->groupRows($sub, $depth + 1)];
        }
        $rows[] = ['code' => '', 'name' => $indent.'Total '.$group['name'], 'amount' => $group['total'], '_style' => 'subtotal'];

        return $rows;
    }

    /** One account's ledger, or every account's (generateAll), with opening and closing rows per account. */
    public function generalLedger(array $data): array
    {
        $ledgers = isset($data['accounts']) ? $data['accounts'] : [$data];
        $rows = [];
        foreach ($ledgers as $ledger) {
            $rows[] = ['date' => '', 'reference_number' => "{$ledger['account']['code']} {$ledger['account']['name']}", '_style' => 'section'];
            $rows[] = ['date' => $data['from'], 'reference_number' => 'Opening Balance', 'balance' => $ledger['opening_balance']];
            foreach ($ledger['lines'] as $line) {
                $rows[] = [
                    'date' => $line['date'],
                    'reference_number' => $line['reference_number'],
                    'description' => $line['description'],
                    'coding' => implode(' / ', array_filter([$line['grant_code'] ?? null, $line['dea_code'] ?? null, $line['project_code'] ?? null, $line['cost_centre_code'] ?? null])),
                    'transacted' => ($line['currency_code'] ?? null) && ($line['currency_code'] !== ($data['currency']['base_currency'] ?? null))
                        ? $line['currency_code'].' '.number_format(($line['transaction_debit'] ?? 0) ?: ($line['transaction_credit'] ?? 0), 2)
                        : '',
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'balance' => $line['balance'],
                ];
            }
            $rows[] = ['date' => $data['to'], 'reference_number' => 'Closing Balance', 'balance' => $ledger['closing_balance'], '_style' => 'subtotal'];
        }

        return $this->table('General Ledger', $this->range($data), $data, [
            $this->col('date', 'Date', 'date'), $this->col('reference_number', 'Reference'), $this->col('description', 'Description'),
            $this->col('coding', 'Coding'), $this->col('transacted', 'As transacted'),
            $this->col('debit', 'Debit', 'money'), $this->col('credit', 'Credit', 'money'), $this->col('balance', 'Balance', 'money'),
        ], $rows, landscape: true);
    }

    public function budgetVsActual(array $data, string $budgetName, string $period): array
    {
        $rows = array_map(fn ($l) => [
            'account_code' => $l['account_code'],
            'account_name' => $l['account_name'],
            'coding' => implode(' / ', array_filter([$l['grant_code'] ?? null, $l['project_code'] ?? null, $l['cost_centre_code'] ?? null])),
            'budgeted_amount' => $l['budgeted_amount'],
            'actual_amount' => $l['actual_amount'],
            'variance' => $l['variance'],
            'utilization_percent' => $l['utilization_percent'],
        ], $data['lines']);
        $t = $data['totals'];
        $rows[] = [
            'account_code' => '', 'account_name' => 'Total', 'coding' => '',
            'budgeted_amount' => $t['budgeted_amount'], 'actual_amount' => $t['actual_amount'], 'variance' => $t['variance'],
            'utilization_percent' => $t['budgeted_amount'] > 0 ? round($t['actual_amount'] / $t['budgeted_amount'] * 100, 2) : null,
            '_style' => 'total',
        ];

        return $this->table("Budget vs Actual: {$budgetName}", $period, $data, [
            $this->col('account_code', 'Code'), $this->col('account_name', 'Account'), $this->col('coding', 'Coding'),
            $this->col('budgeted_amount', 'Budgeted', 'money'), $this->col('actual_amount', 'Actual', 'money'),
            $this->col('variance', 'Variance', 'money'), $this->col('utilization_percent', 'Used %', 'percent'),
        ], $rows, landscape: true);
    }

    /** A Reports Center report: already a table (columns, rows, totals). */
    public function center(array $data, string $title): array
    {
        $columns = array_map(fn ($c) => $this->col($c['key'], $c['label'], $c['type'] ?? 'text'), $data['columns'] ?? []);
        $rows = $data['rows'] ?? [];
        if (! empty($data['totals'])) {
            $first = $columns[0]['key'] ?? null;
            $rows[] = ($first ? [$first => 'Total'] : []) + $data['totals'] + ['_style' => 'total'];
        }

        return $this->table($title, $this->range($data), $data, $columns, $rows, landscape: count($columns) > 6);
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /** CSV: a header row, then one row per line. Money as plain numbers (no symbols, no thousands separators). */
    protected function csv(array $table, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 names correctly
            fputcsv($out, array_column($table['columns'], 'label'));
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map(fn ($c) => $this->plain($row[$c['key']] ?? null, $c['type']), $table['columns']));
            }
            fclose($out);
        }, $filename, ['Content-Type' => self::MIME['csv']]);
    }

    protected function pdf(array $table, User $user): \Barryvdh\DomPDF\PDF
    {
        $company = Company::find($user->company_id);
        $branch = Branch::query()->where('company_id', $user->company_id)->where('is_main', true)->first();

        return Pdf::loadView('pdf.report', [
            'table' => $table,
            'company' => $company,
            'branch' => $branch,
            'generatedBy' => $user->name,
            'generatedAt' => now($company?->timezone ?: config('app.timezone'))->format('Y-m-d H:i'),
        ])->setPaper('a4', ($table['landscape'] ?? false) ? 'landscape' : 'portrait');
    }

    /** A cell value for CSV/Excel: numbers stay numbers, rounded to 2dp; blanks stay blank. */
    public function plain(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        return in_array($type, ['money', 'number', 'percent'], true) && is_numeric($value) ? round((float) $value, 2) : $value;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    protected function table(string $title, string $period, array $data, array $columns, array $rows, bool $landscape = false): array
    {
        $currency = $data['currency'] ?? null;
        $currencyNote = $currency
            ? ($currency['converted'] ?? false
                ? "{$currency['code']} (converted from {$currency['base_currency']} at {$currency['rate']} on {$currency['rate_date']})"
                : $currency['code'])
            : null;

        return [
            'title' => $title,
            'period' => $period,
            'currency' => $currencyNote,
            'filters' => [],
            'columns' => $columns,
            'rows' => $rows,
            'landscape' => $landscape,
        ];
    }

    protected function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    protected function amountColumns(): array
    {
        return [$this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('amount', 'Amount', 'money')];
    }

    protected function accountRows(array $accounts): array
    {
        return array_map(fn ($a) => ['code' => $a['code'], 'name' => $a['name'], 'amount' => $a['amount']], $accounts);
    }

    protected function section(string $title, array $accounts, string $totalLabel, float|int $total): array
    {
        return [
            ['code' => '', 'name' => $title, 'amount' => null, '_style' => 'section'],
            ...$this->accountRows($accounts),
            ['code' => '', 'name' => $totalLabel, 'amount' => $total, '_style' => 'subtotal'],
        ];
    }

    protected function range(array $data): string
    {
        return isset($data['from'], $data['to']) ? "{$data['from']} to {$data['to']}" : '';
    }

    /**
     * Human-readable filters for the file's heading. Only the caller's own
     * company's records are looked up, so an id from another tenant prints as
     * a bare "#id" rather than leaking that tenant's name.
     */
    protected function describeFilters(int $companyId, array $filters): array
    {
        $lookups = [
            'branch_id' => ['Branch', Branch::class, 'name'],
            'grant_id' => ['SOF', Grant::class, 'code'],
            'dea_id' => ['DEA', GrantDea::class, 'code'],
            'project_id' => ['Project', MeProject::class, 'name'],
            'cost_centre_id' => ['Cost centre', CostCentre::class, 'code'],
            'department_id' => ['Department', Department::class, 'name'],
        ];

        $described = [];
        foreach ($lookups as $key => [$label, $model, $field]) {
            if (empty($filters[$key])) {
                continue;
            }
            $name = $model::withoutGlobalScopes()->where('company_id', $companyId)->whereKey((int) $filters[$key])->value($field);
            $described[$label] = $name ?? '#'.$filters[$key];
        }
        foreach (['status' => 'Status', 'group_by' => 'Grouped', 'account_type' => 'Account type', 'source' => 'Source'] as $key => $label) {
            if (! empty($filters[$key]) && $filters[$key] !== 'all') {
                $described[$label] = Str::headline((string) $filters[$key]);
            }
        }

        return $described;
    }

    protected function filename(array $table, string $format): string
    {
        $slug = Str::slug($table['title']);
        $period = Str::slug(str_replace(' to ', '-to-', $table['period']));

        return trim("{$slug}-{$period}", '-').'.'.$format;
    }
}
