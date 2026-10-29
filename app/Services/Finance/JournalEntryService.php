<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\Grants\DimensionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Currency: an entry has a currency_code and exchange_rate (units of that
 * currency per 1 base unit, see ExchangeRateService). Callers always pass
 * line amounts in the entry's own currency as `debit`/`credit`; each line is
 * stored with those as transaction_debit/transaction_credit and with
 * debit/credit in the base currency (amount / rate), which is all the reports
 * ever read. A line may carry an explicit `base_debit`/`base_credit` instead
 * (used for realised FX gain/loss, a base-only line with no transaction
 * amount). With no currency given, an entry is in the base currency at rate 1
 * — so every existing caller posts exactly as before.
 *
 * Coding: any line may carry a SOF/DEA/project/cost centre (see
 * DimensionService), validated against the entry date. The coding sits on
 * the line next to its transaction and base amounts, and the line's entry
 * holds the currency and rate, so every coded line keeps all three.
 */
class JournalEntryService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected DimensionService $dimensionService,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with('lines.account')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('entry_date')
            ->paginate($perPage);
    }

    public function create(array $data): JournalEntry
    {
        $this->assertBalanced($data['lines']);

        $companyId = $data['company_id'] ?? Auth::user()?->company_id;
        [$currency, $rate] = $companyId
            ? $this->exchangeRateService->resolve($companyId, $data['currency_code'] ?? null, $data['entry_date'], $data['exchange_rate'] ?? null)
            : [$data['currency_code'] ?? null, 1.0];
        if ($companyId) {
            $this->dimensionService->validate($companyId, $data['lines'], Carbon::parse($data['entry_date'])->toDateString());
        }
        $lines = $this->normalizeLines($data['lines'], $rate);

        return DB::transaction(function () use ($data, $currency, $rate, $lines) {
            $entry = JournalEntry::create([
                'branch_id' => $data['branch_id'] ?? null,
                'reference_number' => $data['reference_number'],
                'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? null,
                'source_module' => $data['source_module'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'currency_code' => $currency,
                'exchange_rate' => $rate,
            ]);

            $entry->lines()->createMany($lines);

            return $entry->load('lines.account');
        });
    }

    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft journal entries can be edited.'],
            ]);
        }

        if (isset($data['lines'])) {
            $this->assertBalanced($data['lines']);
        }

        // A change of currency, rate or date re-derives the rate and therefore
        // every line's base amount, from the lines given or the stored ones.
        $currencyTouched = array_key_exists('currency_code', $data) || array_key_exists('exchange_rate', $data) || isset($data['entry_date']);
        [$currency, $rate] = $currencyTouched
            ? $this->exchangeRateService->resolve(
                $entry->company_id,
                $data['currency_code'] ?? $entry->currency_code,
                $data['entry_date'] ?? $entry->entry_date->toDateString(),
                array_key_exists('exchange_rate', $data) ? $data['exchange_rate'] : (isset($data['currency_code']) || isset($data['entry_date']) ? null : $entry->exchange_rate),
            )
            : [$entry->currency_code, (float) $entry->exchange_rate];

        $lines = isset($data['lines'])
            ? $this->normalizeLines($data['lines'], $rate)
            : ($currencyTouched ? $this->normalizeLines($entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => (float) $line->transaction_debit,
                'credit' => (float) $line->transaction_credit,
                'description' => $line->description,
            ] + $line->only(DimensionService::KEYS))->all(), $rate) : null);

        if (isset($data['lines']) || isset($data['entry_date'])) {
            $this->dimensionService->validate(
                $entry->company_id,
                $data['lines'] ?? $entry->lines->map(fn ($line) => $line->only(DimensionService::KEYS))->all(),
                Carbon::parse($data['entry_date'] ?? $entry->entry_date)->toDateString(),
            );
        }

        return DB::transaction(function () use ($entry, $data, $currency, $rate, $lines) {
            $entry->update(array_filter([
                'branch_id' => $data['branch_id'] ?? $entry->branch_id,
                'reference_number' => $data['reference_number'] ?? $entry->reference_number,
                'entry_date' => $data['entry_date'] ?? $entry->entry_date,
                'description' => $data['description'] ?? $entry->description,
                'source_module' => $data['source_module'] ?? $entry->source_module,
                'source_id' => $data['source_id'] ?? $entry->source_id,
                'currency_code' => $currency,
                'exchange_rate' => $rate,
            ], fn ($value) => $value !== null));

            if ($lines !== null) {
                $entry->lines()->delete();
                $entry->lines()->createMany($lines);
            }

            return $entry->fresh('lines.account');
        });
    }

    public function delete(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft journal entries can be deleted.'],
            ]);
        }

        $entry->delete();
    }

    /**
     * Used internally by other modules (payroll, depreciation, ...) to create
     * an already-posted, balanced journal entry in a single step.
     */
    public function postModuleEntry(array $data): JournalEntry
    {
        $entry = $this->create($data);

        return $this->post($entry);
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft journal entries can be posted.'],
            ]);
        }

        $this->assertPeriodOpen($entry->company_id, $entry->entry_date->toDateString());

        $entry->update([
            'status' => 'posted',
            'posted_by' => Auth::id(),
        ]);

        return $entry->fresh('lines.account');
    }

    public function reverse(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => ['Only posted journal entries can be reversed.'],
            ]);
        }

        $this->assertPeriodOpen($entry->company_id, $entry->entry_date->toDateString());

        return DB::transaction(function () use ($entry) {
            $reversal = JournalEntry::create([
                'branch_id' => $entry->branch_id,
                'reference_number' => $entry->reference_number.'-REV',
                'entry_date' => now()->toDateString(),
                'description' => "Reversal of {$entry->reference_number}",
                'source_module' => $entry->source_module,
                'source_id' => $entry->source_id,
                'status' => 'posted',
                'posted_by' => Auth::id(),
                'currency_code' => $entry->currency_code,
                'exchange_rate' => $entry->exchange_rate,
            ]);

            // Mirrors the original exactly, base and transaction amounts alike —
            // never re-converted, so the reversal nets it to zero in both.
            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'transaction_debit' => $line->transaction_credit,
                    'transaction_credit' => $line->transaction_debit,
                    'description' => $line->description,
                ] + $line->only(DimensionService::KEYS));
            }

            $entry->update(['status' => 'reversed']);

            return $reversal->load('lines.account');
        });
    }

    protected function assertPeriodOpen(int $companyId, string $entryDate): void
    {
        $isClosed = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                'entry_date' => ['This entry date falls within a closed accounting period and cannot be posted or reversed.'],
            ]);
        }
    }

    /**
     * Stores each line's own-currency amounts and its base amounts (amount /
     * rate, or an explicit base_debit/base_credit). Rounding each line to the
     * cent can leave the base side a few cents out of balance; that residue is
     * absorbed by the largest line on the short side, so the base entry always
     * balances exactly. An imbalance beyond rounding is rejected.
     */
    protected function normalizeLines(array $lines, float $rate = 1.0): array
    {
        $normalized = array_map(function (array $line) use ($rate) {
            $transactionDebit = round((float) ($line['debit'] ?? 0), 2);
            $transactionCredit = round((float) ($line['credit'] ?? 0), 2);

            return [
                'account_id' => $line['account_id'],
                'debit' => array_key_exists('base_debit', $line) ? round((float) $line['base_debit'], 2) : round($transactionDebit / $rate, 2),
                'credit' => array_key_exists('base_credit', $line) ? round((float) $line['base_credit'], 2) : round($transactionCredit / $rate, 2),
                'transaction_debit' => $transactionDebit,
                'transaction_credit' => $transactionCredit,
                'description' => $line['description'] ?? null,
            ] + DimensionService::of($line);
        }, array_values($lines));

        $differenceCents = (int) round(array_sum(array_column($normalized, 'debit')) * 100) - (int) round(array_sum(array_column($normalized, 'credit')) * 100);

        if ($differenceCents !== 0) {
            if (abs($differenceCents) > count($normalized)) {
                throw ValidationException::withMessages([
                    'lines' => ['Journal entry debit and credit totals must balance in the base currency.'],
                ]);
            }

            // Debits exceed credits: top up the largest credit line, and vice versa.
            $side = $differenceCents > 0 ? 'credit' : 'debit';
            $largest = null;
            foreach ($normalized as $index => $line) {
                if ($line[$side] > 0 && ($largest === null || $line[$side] > $normalized[$largest][$side])) {
                    $largest = $index;
                }
            }
            $normalized[$largest][$side] = round($normalized[$largest][$side] + abs($differenceCents) / 100, 2);
        }

        return $normalized;
    }

    protected function assertBalanced(array $lines): void
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += (int) round(($line['debit'] ?? 0) * 100);
            $totalCredit += (int) round(($line['credit'] ?? 0) * 100);
        }

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'lines' => ['Journal entry debit and credit totals must balance.'],
            ]);
        }
    }
}
