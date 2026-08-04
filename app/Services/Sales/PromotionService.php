<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Promotions are advisory: applicableDiscount() computes a suggested
 * discount_amount for a line, but quotation/invoice item creation always
 * lets the caller override it, never force-locks it.
 */
class PromotionService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Promotion::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Promotion
    {
        $promotion = Promotion::create(collect($data)->except('product_ids')->toArray());

        if ($promotion->applies_to === 'specific_products') {
            $promotion->products()->sync($data['product_ids'] ?? []);
        }

        return $promotion->load('products');
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update(collect($data)->except('product_ids')->toArray());

        if ($promotion->applies_to === 'specific_products' && array_key_exists('product_ids', $data)) {
            $promotion->products()->sync($data['product_ids']);
        }

        return $promotion->load('products');
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    public function findApplicablePromotion(Product $product, ?Customer $customer, string $date): ?Promotion
    {
        $date = Carbon::parse($date)->toDateString();

        return Promotion::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($product) {
                $query->where('applies_to', 'all_products')
                    ->orWhere(function ($query) use ($product) {
                        $query->where('applies_to', 'category')->where('category_id', $product->category_id);
                    })
                    ->orWhere(function ($query) use ($product) {
                        $query->where('applies_to', 'specific_products')
                            ->whereHas('products', fn ($query) => $query->where('products.id', $product->id));
                    });
            })
            ->oldest('id')
            ->first();
    }

    /**
     * Suggested discount_amount for `quantity` units of `product` priced at
     * its own selling_price, under the first matching active promotion.
     * buy_x_get_y treats `value` as X in "buy X get 1 free".
     */
    public function applicableDiscount(Product $product, ?Customer $customer, string $date, float $quantity = 1.0): float
    {
        $promotion = $this->findApplicablePromotion($product, $customer, $date);

        if (! $promotion) {
            return 0.0;
        }

        $unitPrice = (float) $product->selling_price;
        $value = (float) $promotion->value;

        return match ($promotion->type) {
            'percentage_discount' => round($unitPrice * $quantity * ($value / 100), 2),
            'fixed_discount' => round($value * $quantity, 2),
            'buy_x_get_y' => round(floor($quantity / ($value + 1)) * $unitPrice, 2),
            default => 0.0,
        };
    }
}
