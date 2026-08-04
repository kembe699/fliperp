<?php

namespace App\Notifications;

use App\Models\Product;

class LowStockAlert extends AppNotification
{
    public function __construct(public Product $product, public float $quantityOnHand) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Low stock alert',
            'body' => "{$this->product->name} dropped to {$this->quantityOnHand} units, below its reorder level of {$this->product->reorder_level}.",
            'link' => "/products/{$this->product->id}",
            'category' => 'low_stock_alert',
        ];
    }
}
