<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Which accounts a product posts to, and the department it is reported under.
 *
 * Every product belongs to a category and a department. The category names
 * its revenue, cost of sales and inventory accounts; any it leaves blank
 * falls back to the default chart (4000 Sales Revenue, 5300 Cost of Goods
 * Sold, 1200 Inventory), so a product posts somewhere sensible even before
 * its category is configured. Sales, invoices and goods received all resolve
 * through here, so one product lands on the same accounts whichever screen
 * moved it.
 */
class ProductAccountResolver
{
    public const DEFAULT_CODES = [
        'revenue' => '4000',
        'cogs' => '5300',
        'inventory' => '1200',
    ];

    /** @var array<int, array{revenue: int, cogs: int, inventory: int, department_id: ?int}> */
    protected array $resolved = [];

    /** @var array<string, int> */
    protected array $defaults = [];

    /**
     * @return array{revenue: int, cogs: int, inventory: int, department_id: ?int}
     */
    public function forProduct(int $companyId, int $productId): array
    {
        if (isset($this->resolved[$productId])) {
            return $this->resolved[$productId];
        }

        $product = Product::withTrashed()->with('category')->find($productId);
        $category = $product?->category;

        return $this->resolved[$productId] = [
            'revenue' => $category?->revenue_account_id ?? $this->defaultAccountId($companyId, 'revenue'),
            'cogs' => $category?->cogs_account_id ?? $this->defaultAccountId($companyId, 'cogs'),
            'inventory' => $category?->inventory_account_id ?? $this->defaultAccountId($companyId, 'inventory'),
            'department_id' => $product?->department_id,
        ];
    }

    /**
     * Splits $total across $weights (keyed as the caller likes) in proportion,
     * the last key taking any rounding cent, so the parts always sum to $total.
     *
     * @param  array<string, float>  $weights
     * @return array<string, float>
     */
    public static function split(float $total, array $weights): array
    {
        $sum = array_sum($weights);
        $keys = array_keys($weights);
        $parts = [];
        $allocated = 0.0;

        foreach ($keys as $index => $key) {
            $amount = $index === count($keys) - 1
                ? round($total - $allocated, 2)
                : ($sum > 0 ? round($total * $weights[$key] / $sum, 2) : 0.0);
            $allocated = round($allocated + $amount, 2);
            $parts[$key] = $amount;
        }

        return $parts;
    }

    protected function defaultAccountId(int $companyId, string $kind): int
    {
        $code = self::DEFAULT_CODES[$kind];

        return $this->defaults[$code] ??= ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->value('id') ?? throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."],
            ]);
    }
}
