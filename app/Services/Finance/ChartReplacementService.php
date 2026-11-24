<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\CompanySystemAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Replaces a company's whole chart of accounts with one it supplies (G711's
 * donor chart), before it has posted anything.
 *
 * In one transaction: the old accounts' codes are moved aside, the new
 * accounts are created with their parents and heading/posting kind, every
 * reference to an old account is pointed at its new account (from the
 * file's replace_old_accounts map), the system accounts are set, and the old
 * accounts are deleted. It refuses, changing nothing, if the company has any
 * journal lines, or if something still refers to an old account the file
 * doesn't say how to replace.
 *
 * File format: database/data/g711_chart_of_accounts.json.
 */
class ChartReplacementService
{
    public function __construct(protected SystemAccountService $systemAccounts) {}

    /**
     * @param  array{accounts: list<array>, system_accounts: array<string,string>, replace_old_accounts?: array<string,string>}  $chart
     * @return array<string, mixed> what was (or, on a dry run, would be) done
     */
    public function replace(Company $company, array $chart, bool $apply): array
    {
        $this->validateChart($chart);

        DB::beginTransaction();
        try {
            $result = (function () use ($company, $chart) {
            $companyId = $company->id;
            $old = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->get()->keyBy('id');

            $lines = DB::table('journal_entry_lines')->whereIn('account_id', $old->keys())->count();
            if ($lines > 0) {
                throw new RuntimeException("{$company->name} already has {$lines} journal lines; replacing its chart would orphan them.");
            }

            // Move the old codes aside so the new chart can reuse them.
            foreach ($old as $account) {
                DB::table('chart_of_accounts')->where('id', $account->id)->update(['code' => 'OLD-'.$account->id.'-'.$account->code]);
            }

            $byCode = [];
            foreach ($chart['accounts'] as $row) {
                $byCode[$row['code']] = ChartOfAccount::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'is_active' => $row['is_active'] ?? true,
                    'is_postable' => $row['is_postable'],
                ]);
            }
            foreach ($chart['accounts'] as $row) {
                if ($row['parent_code']) {
                    $byCode[$row['code']]->update(['parent_id' => $byCode[$row['parent_code']]->id]);
                }
            }

            // Point everything that used an old account at its new one.
            CompanySystemAccount::query()->where('company_id', $companyId)->delete();
            $replace = $chart['replace_old_accounts'] ?? [];
            $moved = [];
            $unplaced = [];
            foreach ($this->referencingColumns() as [$table, $column]) {
                if ($table === 'chart_of_accounts' || $table === 'company_system_accounts') {
                    continue;
                }
                foreach (DB::table($table)->whereIn($column, $old->keys())->select('id', $column)->get() as $ref) {
                    $oldAccount = $old[$ref->{$column}];
                    $newCode = $replace[$oldAccount->code] ?? null;
                    if (! $newCode) {
                        $unplaced[] = "{$table}.{$column} #{$ref->id} uses {$oldAccount->code} {$oldAccount->name}";

                        continue;
                    }
                    DB::table($table)->where('id', $ref->id)->update([$column => $byCode[$newCode]->id]);
                    $moved[] = "{$table}.{$column} #{$ref->id}: {$oldAccount->code} {$oldAccount->name} -> {$newCode} {$byCode[$newCode]->name}";
                }
            }
            if ($unplaced) {
                throw new RuntimeException("These still use old accounts and the file doesn't say where they go:\n  ".implode("\n  ", $unplaced));
            }

            $this->systemAccounts->update($companyId, collect($chart['system_accounts'])->map(fn ($code) => $byCode[$code]->id)->all());

            DB::table('chart_of_accounts')->whereIn('id', $old->keys())->update(['parent_id' => null]);
            DB::table('chart_of_accounts')->whereIn('id', $old->keys())->delete();

            return [
                'company' => "{$company->name} (#{$companyId})",
                'old_accounts_deleted' => $old->map(fn ($a) => "{$a->code} {$a->name}")->values()->all(),
                'accounts_created' => count($byCode),
                'headings' => collect($chart['accounts'])->where('is_postable', false)->count(),
                'references_moved' => $moved,
                'system_accounts' => collect($chart['system_accounts'])->map(fn ($code) => "{$code} {$byCode[$code]->name}")->all(),
            ];
            })();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        // A dry run does everything, reports it, and undoes it.
        $apply ? DB::commit() : DB::rollBack();

        return $result + ['applied' => $apply];
    }

    /** @return list<array{0: string, 1: string}> every table/column with a foreign key to chart_of_accounts */
    protected function referencingColumns(): array
    {
        return collect(DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS tbl, a.attname AS col
              FROM pg_constraint c
              JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
             WHERE c.contype = 'f' AND c.confrelid = 'chart_of_accounts'::regclass
        SQL))->map(fn ($row) => [$row->tbl, $row->col])->all();
    }

    protected function validateChart(array $chart): void
    {
        $codes = collect($chart['accounts'])->pluck('code');
        if ($codes->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Duplicate account codes: '.$codes->duplicates()->implode(', '));
        }
        foreach ($chart['accounts'] as $row) {
            if ($row['parent_code'] && ! $codes->contains($row['parent_code'])) {
                throw new RuntimeException("{$row['code']} has parent {$row['parent_code']}, which isn't in the chart.");
            }
            if (! in_array($row['type'], ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
                throw new RuntimeException("{$row['code']} has an unknown type {$row['type']}.");
            }
        }
        $missing = array_diff(array_keys(SystemAccountService::FUNCTIONS), array_keys($chart['system_accounts']));
        if ($missing) {
            throw new RuntimeException('No account given for: '.implode(', ', $missing));
        }
        $rows = collect($chart['accounts'])->keyBy('code');
        foreach ($chart['system_accounts'] + ($chart['replace_old_accounts'] ?? []) as $key => $code) {
            $row = $rows[$code] ?? throw new RuntimeException("{$key} points to {$code}, which isn't in the chart.");
            if (! $row['is_postable'] || ! ($row['is_active'] ?? true)) {
                throw new RuntimeException("{$key} points to {$code} {$row['name']}, which is a heading or inactive.");
            }
        }
    }
}
