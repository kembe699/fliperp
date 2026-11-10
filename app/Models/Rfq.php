<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A request for quotation sent to one or more suppliers; can become a purchase order. */
class Rfq extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'company_id', 'branch_id', 'warehouse_id', 'reference_number', 'title', 'issue_date', 'response_due_date',
        'status', 'notes', 'selected_supplier_id', 'purchase_order_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'response_due_date' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(RfqSupplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function selectedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'selected_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
