<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Finance\ChartReplacementService;
use Illuminate\Console\Command;

/**
 * php artisan accounts:replace-chart G711 database/data/g711_chart_of_accounts.json          (dry run)
 * php artisan accounts:replace-chart G711 database/data/g711_chart_of_accounts.json --apply
 */
class ReplaceChartOfAccounts extends Command
{
    protected $signature = 'accounts:replace-chart {client_code} {file} {--apply : Make the change (without it, a dry run that changes nothing)}';

    protected $description = "Replace a company's chart of accounts and system accounts with the ones in a JSON file";

    public function handle(ChartReplacementService $service): int
    {
        $company = Company::query()->where('client_code', $this->argument('client_code'))->first();
        if (! $company) {
            $this->error('No company with client code '.$this->argument('client_code'));

            return self::FAILURE;
        }
        $chart = json_decode((string) file_get_contents(base_path($this->argument('file'))), true, flags: JSON_THROW_ON_ERROR);

        $result = $service->replace($company, $chart, (bool) $this->option('apply'));

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info($result['applied'] ? 'Applied.' : 'Dry run: nothing was changed. Run again with --apply.');

        return self::SUCCESS;
    }
}
