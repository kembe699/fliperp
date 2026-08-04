<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;

class PurchaseOrderApprovalNeeded extends AppNotification
{
    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Purchase order needs approval',
            'body' => "PO {$this->purchaseOrder->reference_number} from {$this->purchaseOrder->supplier->name} is awaiting approval.",
            'link' => "/purchase-orders/{$this->purchaseOrder->id}",
            'category' => 'purchase_order_approval_needed',
        ];
    }
}
