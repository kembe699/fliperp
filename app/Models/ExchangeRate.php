<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

/**
 * `rate` = units of currency_code per 1 unit of the company's base currency
 * ("1 USD = 7,800 SSP" is 7800), effective from effective_date until the next
 * row for the same currency.
 */
class ExchangeRate extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id',
        'currency_code',
        'rate',
        'effective_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'effective_date' => 'date',
        ];
    }
}
