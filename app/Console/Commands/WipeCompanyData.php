<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Clears one company's business data so it starts clean: sales, invoices,
 * purchases, stock, journal entries, customers, suppliers, products, HR and
 * CRM records. What it needs to log in and work is kept: the company itself,
 * its users and roles, branches, warehouses and the setup lists below, and its
 * chart of accounts (opening balances reset to zero).
 *
 * Every table with a company_id is cleared for that company, and rows in other
 * tables that point at those rows (invoice lines, journal lines, ...) are
 * deleted with them, found from the database's foreign keys. Kept rows that
 * point at deleted ones have that column set to null. All in one transaction.
 * Dry run (counts only) unless --confirm.
 */
class WipeCompanyData extends Command
{
    protected $signature = 'company:wipe {client_code : The company\'s client code, e.g. G711} {--confirm : Delete (default is a dry run)}';

    protected $description = 'Delete all business data of one company, keeping its users, roles, setup and chart of accounts';

    /** Company tables that are kept. */
    private const KEEP = [
        'users', 'roles', 'branches', 'warehouses', 'departments', 'positions', 'chart_of_accounts',
        'accounting_periods', 'tax_rates', 'payment_types', 'units_of_measure', 'leave_types',
        'statutory_deduction_rules', 'crm_pipeline_stages', 'attendance_geofences', 'platform_tickets',
    ];

    /** @var array<string, list<array{child: string, column: string, nullable: bool}>> */
    private array $referencedBy = [];

    /** @var array<string, int> */
    private array $counts = [];

    private bool $apply = false;

    public function handle(): int
    {
        $code = (string) $this->argument('client_code');
        $company = Company::where('client_code', $code)->first();
        if (! $company) {
            $this->error("No company with client code {$code}.");

            return self::FAILURE;
        }
        if ($company->is_platform) {
            $this->error("{$code} is the platform company; refusing.");

            return self::FAILURE;
        }

        $this->apply = (bool) $this->option('confirm');
        $this->loadForeignKeys();
        $tables = collect(DB::select(
            "SELECT table_name FROM information_schema.columns
              WHERE column_name = 'company_id' AND table_schema = current_schema() AND table_name <> 'companies'
              ORDER BY table_name"
        ))->pluck('table_name')->reject(fn ($t) => in_array($t, self::KEEP, true))->values();

        $this->info(($this->apply ? 'Deleting' : 'Dry run for')." {$company->name} ({$code}, id {$company->id})");

        DB::transaction(function () use ($tables, $company) {
            foreach ($tables as $table) {
                $this->purge($table, '"company_id" = '.(int) $company->id, [$table]);
            }
            if ($this->apply) {
                DB::table('chart_of_accounts')->where('company_id', $company->id)
                    ->update(['opening_balance' => 0, 'opening_balance_date' => null, 'opening_balance_entry_id' => null]);
            }
        });

        $rows = collect($this->counts)->filter()->sortKeys()->map(fn ($n, $t) => [$t, $n])->values()->all();
        $this->table(['Table', $this->apply ? 'Rows deleted' : 'Rows to delete'], $rows);
        $this->line('Kept: company, '.implode(', ', self::KEEP).' (opening balances reset to 0).');
        if (! $this->apply) {
            $this->warn('Nothing deleted. Run again with --confirm to delete.');
        }

        return self::SUCCESS;
    }

    /** Delete (or count) the rows of $table matching $where, and everything that points at them first. */
    private function purge(string $table, string $where, array $path): void
    {
        $selector = "SELECT id FROM \"{$table}\" WHERE {$where}";
        foreach ($this->referencedBy[$table] ?? [] as $fk) {
            $match = "\"{$fk['column']}\" IN ({$selector})";
            $keep = in_array($fk['child'], self::KEEP, true) || $fk['child'] === 'companies';
            if ($keep || in_array($fk['child'], $path, true)) {
                if ($this->apply) {
                    if (! $fk['nullable']) {
                        throw new RuntimeException("{$fk['child']}.{$fk['column']} points at {$table} and can't be cleared.");
                    }
                    DB::update("UPDATE \"{$fk['child']}\" SET \"{$fk['column']}\" = NULL WHERE {$match}");
                }

                continue;
            }
            $this->purge($fk['child'], $match, [...$path, $fk['child']]);
        }

        $sql = $this->apply ? "DELETE FROM \"{$table}\" WHERE {$where}" : "SELECT COUNT(*) AS n FROM \"{$table}\" WHERE {$where}";
        $n = $this->apply ? DB::delete($sql) : (int) DB::selectOne($sql)->n;
        $this->counts[$table] = ($this->counts[$table] ?? 0) + $n;
    }

    private function loadForeignKeys(): void
    {
        $rows = DB::select(
            "SELECT child.relname AS child, att.attname AS column, parent.relname AS parent, NOT att.attnotnull AS nullable
               FROM pg_constraint c
               JOIN pg_class child ON child.oid = c.conrelid
               JOIN pg_class parent ON parent.oid = c.confrelid
               JOIN pg_namespace ns ON ns.oid = child.relnamespace AND ns.nspname = current_schema()
               JOIN pg_attribute att ON att.attrelid = c.conrelid AND att.attnum = c.conkey[1]
              WHERE c.contype = 'f' AND array_length(c.conkey, 1) = 1"
        );
        foreach ($rows as $r) {
            $this->referencedBy[$r->parent][] = ['child' => $r->child, 'column' => $r->column, 'nullable' => (bool) $r->nullable];
        }
    }
}
