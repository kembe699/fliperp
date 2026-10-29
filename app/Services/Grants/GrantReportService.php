<?php

namespace App\Services\Grants;

use App\Models\Grant;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Finance\ExchangeRateService;
use Illuminate\Support\Collection;

/**
 * SOF Grant Report: a grant's donor budget against what has been charged to
 * it, per account and DEA, in two currencies side by side —
 *
 *  - base: the ledger's own base-currency amounts (the `*_base` keys; these
 *    are what the report's "Show in" option converts, like any report);
 *  - SOF: the grant's own currency, which the donor is reported to in (the
 *    `*_sof` keys, never converted). A line transacted in the SOF currency
 *    counts at its own transaction amount; any other line is converted from
 *    base at the rate in force on its entry date.
 *
 * Spend is the net debit on every non-revenue account coded to the SOF;
 * revenue coded to it (donor income) is shown separately as income.
 */
class GrantReportService
{
    public function __construct(protected ExchangeRateService $exchangeRateService) {}

    public function generate(Grant $grant, ?string $from = null, ?string $to = null): array
    {
        $from ??= $grant->start_date->toDateString();
        $to ??= $grant->end_date->toDateString();
        $grant->loadMissing(['donor', 'budgetLines.account', 'budgetLines.dea']);

        $lines = $this->codedLines($grant, $from, $to);
        $missingRates = [];
        $transactions = $lines->map(fn ($line) => $this->transaction($grant, $line, $missingRates))->values();

        $spend = $transactions->where('is_income', false);
        $income = $transactions->where('is_income', true);

        $rows = $this->rows($grant, $spend);
        $budget = round((float) $grant->budget_amount, 2);
        $budgetedLines = round($rows->sum('budget_sof'), 2);
        $actualSof = round($rows->sum('actual_sof'), 2);
        $actualBase = round($rows->sum('actual_base'), 2);

        $days = max(1, (int) $grant->start_date->diffInDays($grant->end_date) + 1);
        $elapsed = now()->startOfDay()->lt($grant->start_date) ? 0 : min($days, (int) $grant->start_date->diffInDays(now()->startOfDay()) + 1);

        return [
            'grant' => [
                'id' => $grant->id,
                'code' => $grant->code,
                'name' => $grant->name,
                'donor' => $grant->donor?->name,
                'currency_code' => $grant->currency_code,
                'start_date' => $grant->start_date->toDateString(),
                'end_date' => $grant->end_date->toDateString(),
                'status' => $grant->status,
            ],
            'from' => $from,
            'to' => $to,
            'sof_currency' => $grant->currency_code,
            'base_currency' => $this->exchangeRateService->baseCurrency($grant->company_id),
            'rows' => $rows->values()->all(),
            'totals' => [
                'award_sof' => $budget,
                'budget_sof' => $budgetedLines,
                'actual_sof' => $actualSof,
                'actual_base' => $actualBase,
                'variance_sof' => round($budgetedLines - $actualSof, 2),
                'remaining_award_sof' => round($budget - $actualSof, 2),
                'burn_percent' => $budget > 0 ? round($actualSof / $budget * 100, 2) : null,
                'time_elapsed_percent' => round($elapsed / $days * 100, 2),
                'income_sof' => round($income->sum('actual_sof'), 2),
                'income_base' => round($income->sum('actual_base'), 2),
            ],
            'transactions' => $transactions->all(),
            'missing_rates' => array_values(array_unique($missingRates)),
        ];
    }

    /** Award, spend and burn for every SOF of the company — the grants list. */
    public function summary(int $companyId): array
    {
        return Grant::query()->where('company_id', $companyId)->with('donor')->orderBy('code')->get()
            ->map(function (Grant $grant) {
                $report = $this->generate($grant);

                return [
                    'grant_id' => $grant->id,
                    'actual_sof' => $report['totals']['actual_sof'],
                    'actual_base' => $report['totals']['actual_base'],
                    'remaining_award_sof' => $report['totals']['remaining_award_sof'],
                    'burn_percent' => $report['totals']['burn_percent'],
                ];
            })->all();
    }

    protected function codedLines(Grant $grant, string $from, string $to): Collection
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*', 'journal_entries.entry_date', 'journal_entries.reference_number', 'journal_entries.currency_code', 'journal_entries.exchange_rate')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $grant->company_id)
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->where('journal_entry_lines.grant_id', $grant->id)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->with(['account:id,code,name,type', 'dea:id,code,name', 'project:id,code,name', 'costCentre:id,code'])
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->get();
    }

    protected function transaction(Grant $grant, JournalEntryLine $line, array &$missingRates): array
    {
        $isIncome = $line->account->type === 'revenue';
        $sign = $isIncome ? -1 : 1;
        $base = round($sign * ((float) $line->debit - (float) $line->credit), 2);
        $own = round($sign * ((float) $line->transaction_debit - (float) $line->transaction_credit), 2);
        $date = substr((string) $line->entry_date, 0, 10);

        $sof = match (true) {
            $line->currency_code === $grant->currency_code => $own,
            default => $this->baseToSof($grant, $base, $date, $missingRates),
        };

        return [
            'journal_entry_line_id' => $line->id,
            'date' => $date,
            'reference_number' => $line->reference_number,
            'description' => $line->description,
            'account_id' => $line->account_id,
            'account_code' => $line->account->code,
            'account_name' => $line->account->name,
            'dea_id' => $line->dea_id,
            'dea_code' => $line->dea?->code,
            'dea_name' => $line->dea?->name,
            'project_code' => $line->project ? ($line->project->code ?: $line->project->name) : null,
            'cost_centre_code' => $line->costCentre?->code,
            'currency_code' => $line->currency_code,
            'exchange_rate' => (float) $line->exchange_rate,
            'transaction_amount' => $own,
            'actual_base' => $base,
            'actual_sof' => $sof,
            'is_income' => $isIncome,
        ];
    }

    protected function baseToSof(Grant $grant, float $base, string $date, array &$missingRates): float
    {
        if ($grant->currency_code === $this->exchangeRateService->baseCurrency($grant->company_id)) {
            return $base;
        }

        $rate = $this->exchangeRateService->findRate($grant->company_id, $grant->currency_code, $date);
        if (! $rate) {
            $missingRates[] = $date;

            return 0.0;
        }

        return round($base * (float) $rate->rate, 2);
    }

    /** One row per account + DEA that is budgeted or has spend. */
    protected function rows(Grant $grant, Collection $spend): Collection
    {
        $rows = [];
        $key = fn ($accountId, $deaId) => $accountId.'-'.($deaId ?? 0);

        foreach ($grant->budgetLines as $budgetLine) {
            $rows[$key($budgetLine->account_id, $budgetLine->dea_id)] = [
                'account_id' => $budgetLine->account_id,
                'account_code' => $budgetLine->account->code,
                'account_name' => $budgetLine->account->name,
                'dea_id' => $budgetLine->dea_id,
                'dea_code' => $budgetLine->dea?->code,
                'dea_name' => $budgetLine->dea?->name,
                'budget_sof' => round((float) $budgetLine->budgeted_amount, 2),
                'actual_sof' => 0.0,
                'actual_base' => 0.0,
            ];
        }

        foreach ($spend as $transaction) {
            $k = $key($transaction['account_id'], $transaction['dea_id']);
            $rows[$k] ??= [
                'account_id' => $transaction['account_id'],
                'account_code' => $transaction['account_code'],
                'account_name' => $transaction['account_name'],
                'dea_id' => $transaction['dea_id'],
                'dea_code' => $transaction['dea_code'],
                'dea_name' => $transaction['dea_name'],
                'budget_sof' => 0.0,
                'actual_sof' => 0.0,
                'actual_base' => 0.0,
            ];
            $rows[$k]['actual_sof'] = round($rows[$k]['actual_sof'] + $transaction['actual_sof'], 2);
            $rows[$k]['actual_base'] = round($rows[$k]['actual_base'] + $transaction['actual_base'], 2);
        }

        return collect($rows)
            ->map(fn ($row) => $row + [
                'variance_sof' => round($row['budget_sof'] - $row['actual_sof'], 2),
                'utilization_percent' => $row['budget_sof'] > 0 ? round($row['actual_sof'] / $row['budget_sof'] * 100, 2) : null,
            ])
            ->sortBy(fn ($row) => $row['account_code'].'|'.($row['dea_code'] ?? ''))
            ->values();
    }
}
