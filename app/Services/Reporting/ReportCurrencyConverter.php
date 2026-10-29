<?php

namespace App\Services\Reporting;

use App\Services\Finance\ExchangeRateService;
use Illuminate\Validation\ValidationException;

/**
 * Re-expresses a generated report — every figure of which is in the base
 * currency the ledger is kept in — in another currency, for display only.
 * One rate is used for the whole report: the one in force on $asOf (the
 * report's end date, or a balance sheet's as-of date). The result always
 * carries a `currency` block saying which currency its figures are in and,
 * when converted, the rate and the date of the rate used, so a converted
 * report can never pass for ledger figures.
 */
class ReportCurrencyConverter
{
    /** Keys holding money in the report shapes of the Reporting services. */
    protected const MONEY_KEYS = [
        'debit', 'credit', 'balance', 'amount', 'total',
        'total_debit', 'total_credit', 'opening_balance', 'closing_balance',
        'gross_profit', 'net_profit', 'net_income', 'total_liabilities_and_equity',
        'cash_in', 'cash_out', 'net', 'net_cash_movement',
        'budgeted_amount', 'actual_amount', 'variance', 'actual_base', 'income_base',
    ];

    public function __construct(protected ExchangeRateService $exchangeRateService) {}

    public function convert(array $report, int $companyId, ?string $currency, string $asOf): array
    {
        $base = $this->exchangeRateService->baseCurrency($companyId);
        $code = strtoupper($currency ?: $base);

        if ($code === $base) {
            return $report + ['currency' => ['code' => $base, 'base_currency' => $base, 'converted' => false]];
        }

        $rate = $this->exchangeRateService->findRate($companyId, $code, $asOf);

        if (! $rate) {
            throw ValidationException::withMessages([
                'currency' => ["No {$code} exchange rate is set on or before {$asOf}, so this report can't be shown in {$code}."],
            ]);
        }

        return $this->scale($report, (float) $rate->rate) + ['currency' => [
            'code' => $code,
            'base_currency' => $base,
            'converted' => true,
            'rate' => (float) $rate->rate,
            'rate_date' => $rate->effective_date->toDateString(),
        ]];
    }

    protected function scale(array $data, float $rate): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->scale($value, $rate);
            } elseif (is_string($key) && in_array($key, self::MONEY_KEYS, true) && is_numeric($value)) {
                $data[$key] = round((float) $value * $rate, 2);
            }
        }

        return $data;
    }
}
