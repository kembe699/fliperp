<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\ChartOfAccount;
use App\Services\Finance\JournalEntryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepreciationService
{
    public const DEPRECIATION_EXPENSE_ACCOUNT_CODE = '5100';

    public const ACCUMULATED_DEPRECIATION_ACCOUNT_CODE = '1900';

    public function __construct(protected JournalEntryService $journalEntryService) {}

    public function run(?string $periodDate = null): array
    {
        $companyId = Auth::user()->company_id;
        $periodDate = $periodDate ? Carbon::parse($periodDate) : now();

        return DB::transaction(function () use ($companyId, $periodDate) {
            $assets = Asset::query()
                ->with('category')
                ->where('status', '!=', 'disposed')
                ->get();

            $schedules = [];
            $totalDepreciation = 0.0;

            foreach ($assets as $asset) {
                $amount = $this->calculatePeriodDepreciation($asset);

                if ($amount <= 0) {
                    continue;
                }

                $lastSchedule = $asset->depreciationSchedules()->latest('period_date')->first();
                $accumulated = (float) ($lastSchedule->accumulated_depreciation ?? 0) + $amount;
                $bookValue = max((float) $asset->purchase_cost - $accumulated, 0);

                $schedule = AssetDepreciationSchedule::create([
                    'asset_id' => $asset->id,
                    'period_date' => $periodDate->toDateString(),
                    'depreciation_amount' => $amount,
                    'accumulated_depreciation' => $accumulated,
                    'book_value' => $bookValue,
                ]);

                $asset->update(['current_value' => $bookValue]);

                $schedules[] = $schedule;
                $totalDepreciation += $amount;
            }

            if ($totalDepreciation <= 0) {
                return ['schedules' => $schedules, 'journal_entry' => null];
            }

            $journalEntry = $this->postDepreciationJournal($companyId, $periodDate, $totalDepreciation);

            foreach ($schedules as $schedule) {
                $schedule->update(['journal_entry_id' => $journalEntry->id]);
            }

            return ['schedules' => $schedules, 'journal_entry' => $journalEntry];
        });
    }

    protected function calculatePeriodDepreciation(Asset $asset): float
    {
        $category = $asset->category;

        if (! $category || $category->useful_life_years <= 0) {
            return 0.0;
        }

        $lastSchedule = $asset->depreciationSchedules()->latest('period_date')->first();
        $bookValue = (float) ($lastSchedule->book_value ?? $asset->purchase_cost);

        if ($bookValue <= 0) {
            return 0.0;
        }

        if ($category->depreciation_method === 'reducing_balance') {
            $monthlyRate = (2 / $category->useful_life_years) / 12;
            $amount = $bookValue * $monthlyRate;
        } else {
            $amount = ((float) $asset->purchase_cost / $category->useful_life_years) / 12;
        }

        return round(min($amount, $bookValue), 2);
    }

    protected function postDepreciationJournal(int $companyId, Carbon $periodDate, float $totalDepreciation)
    {
        $expenseAccount = $this->resolveAccount($companyId, self::DEPRECIATION_EXPENSE_ACCOUNT_CODE);
        $accumulatedAccount = $this->resolveAccount($companyId, self::ACCUMULATED_DEPRECIATION_ACCOUNT_CODE);

        return $this->journalEntryService->postModuleEntry([
            'reference_number' => 'DEPR-'.$periodDate->format('Ym').'-'.now()->format('His'),
            'entry_date' => $periodDate->toDateString(),
            'description' => "Depreciation run for {$periodDate->format('F Y')}",
            'source_module' => 'assets',
            'lines' => [
                ['account_id' => $expenseAccount->id, 'debit' => $totalDepreciation, 'credit' => 0, 'description' => 'Depreciation expense'],
                ['account_id' => $accumulatedAccount->id, 'debit' => 0, 'credit' => $totalDepreciation, 'description' => 'Accumulated depreciation'],
            ],
        ]);
    }

    protected function resolveAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."],
            ]);
        }

        return $account;
    }
}
