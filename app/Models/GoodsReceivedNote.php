<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceivedNote extends TenantModel
{
    use Auditable;

    protected $table = 'goods_received_notes';

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'warehouse_id',
        'supplier_id',
        'reference_number',
        'received_date',
        'status',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class, 'grn_id');
    }

    public function bill(): HasMany
    {
        return $this->hasMany(SupplierBill::class, 'grn_id');
    }
}
