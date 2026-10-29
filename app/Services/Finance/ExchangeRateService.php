<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Rates are "units of a currency per 1 base unit", so a document amount
 * converts to base as amount / rate, and a base figure converts to another
 * currency as amount * rate.
 */
class ExchangeRateService
{
    public const FX_GAIN_LOSS_ACCOUNT_CODE = '5500';

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return ExchangeRate::query()
            ->when($filters['currency_code'] ?? null, fn ($query, $code) => $query->where('currency_code', strtoupper($code)))
            ->orderByDesc('effective_date')
            ->orderBy('currency_code')
            ->paginate($perPage);
    }

    public function create(array $data): ExchangeRate
    {
        $this->assertNotBase($data['currency_code']);

        return ExchangeRate::create([
            'currency_code' => strtoupper($data['currency_code']),
            'rate' => $data['rate'],
            'effective_date' => $data['effective_date'],
            'created_by' => Auth::id(),
        ]);
    }

    public function update(ExchangeRate $exchangeRate, array $data): ExchangeRate
    {
        if (isset($data['currency_code'])) {
            $this->assertNotBase($data['currency_code']);
            $data['currency_code'] = strtoupper($data['currency_code']);
        }

        $exchangeRate->update($data);

        return $exchangeRate;
    }

    public function delete(ExchangeRate $exchangeRate): void
    {
        $exchangeRate->delete();
    }

    /** Distinct currencies the current company has rates for. */
    public function ratedCurrencies(): array
    {
        return ExchangeRate::query()->distinct()->orderBy('currency_code')->pluck('currency_code')->all();
    }

    public function baseCurrency(int $companyId): string
    {
        return Company::query()->whereKey($companyId)->value('currency_code') ?? 'USD';
    }

    /**
     * The rate row in force on $date: the latest on or before it. Null when
     * the currency has no rate that early. The base currency is not stored.
     */
    public function findRate(int $companyId, string $currencyCode, string $date): ?ExchangeRate
    {
        return ExchangeRate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('currency_code', strtoupper($currencyCode))
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();
    }

    /**
     * [currency, rate] for a document: the base currency is always rate 1; an
     * explicit $override wins; otherwise the rate in force on $date, which
     * must exist. $field names the request field errors are reported on.
     *
     * @return array{0: string, 1: float}
     */
    public function resolve(int $companyId, ?string $currencyCode, string $date, mixed $override = null, string $field = 'exchange_rate'): array
    {
        $base = $this->baseCurrency($companyId);
        $code = strtoupper($currencyCode ?: $base);

        if ($code === $base) {
            return [$code, 1.0];
        }

        if ($override !== null && $override !== '') {
            if ((float) $override <= 0) {
                throw ValidationException::withMessages([$field => ['The exchange rate must be greater than zero.']]);
            }

            return [$code, (float) $override];
        }

        $rate = $this->findRate($companyId, $code, $date);

        if (! $rate) {
            throw ValidationException::withMessages([
                $field => ["No {$code} exchange rate is set on or before {$date}. Add one under Exchange Rates, or enter the rate on this document."],
            ]);
        }

        return [$code, (float) $rate->rate];
    }

    /**
     * The journal line for a realised FX difference on settling a document at
     * a different rate from the one it was booked at: $gain > 0 credits the
     * FX account (a gain), $gain < 0 debits it (a loss). It has no amount in
     * the document's currency — the difference exists only in base. Null when
     * there is nothing to post. The account is created on first use for
     * companies set up before it was part of the default chart.
     */
    public function realisedFxLine(int $companyId, float $gain): ?array
    {
        $gain = round($gain, 2);

        if ($gain == 0.0) {
            return null;
        }

        $account = ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'code' => self::FX_GAIN_LOSS_ACCOUNT_CODE],
            ['name' => 'Realised FX Gain/Loss', 'type' => 'expense', 'is_active' => true],
        );

        return [
            'account_id' => $account->id,
            'debit' => 0,
            'credit' => 0,
            'base_debit' => $gain < 0 ? -$gain : 0,
            'base_credit' => $gain > 0 ? $gain : 0,
            'description' => $gain > 0 ? 'Realised exchange gain' : 'Realised exchange loss',
        ];
    }

    protected function assertNotBase(string $currencyCode): void
    {
        $base = $this->baseCurrency(Auth::user()->company_id);

        if (strtoupper($currencyCode) === $base) {
            throw ValidationException::withMessages([
                'currency_code' => ["{$base} is the base currency; its rate is always 1."],
            ]);
        }
    }
}
