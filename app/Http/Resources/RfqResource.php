<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'title' => $this->title,
            'issue_date' => $this->issue_date?->toDateString(),
            'response_due_date' => $this->response_due_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'items_count' => $this->items_count ?? ($this->relationLoaded('items') ? $this->items->count() : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product?->name,
                'variant_name' => $item->variant?->name,
                'quantity' => (float) $item->quantity,
                'description' => $item->description,
            ])->values()),
            // Each supplier asked: sent or not, quoted or not, prices per item and the quote total.
            'suppliers' => $this->whenLoaded('suppliers', fn () => $this->suppliers->map(function ($line) {
                $prices = $line->relationLoaded('quotes') ? $line->quotes->pluck('unit_price', 'rfq_item_id')->map(fn ($p) => (float) $p) : collect();
                $total = $this->relationLoaded('items')
                    ? $this->items->sum(fn ($item) => isset($prices[$item->id]) ? $prices[$item->id] * (float) $item->quantity : 0)
                    : null;

                return [
                    'supplier_id' => $line->supplier_id,
                    'name' => $line->supplier?->name,
                    'email' => $line->supplier?->email,
                    'sent_at' => $line->sent_at?->toIso8601String(),
                    'sent_to' => $line->sent_to,
                    'quoted_at' => $line->quoted_at?->toIso8601String(),
                    'delivery_days' => $line->delivery_days,
                    'quote_notes' => $line->quote_notes,
                    'prices' => $prices,
                    'quote_total' => $line->quoted_at ? round($total, 2) : null,
                    'items_priced' => $prices->count(),
                ];
            })->values()),
            'selected_supplier_id' => $this->selected_supplier_id,
            'selected_supplier_name' => $this->whenLoaded('selectedSupplier', fn () => $this->selectedSupplier?->name),
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_reference' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder?->reference_number),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
