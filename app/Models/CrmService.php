<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class CrmService extends TenantModel
{
    protected $fillable = [
        'company_id',
        'product_id',
        'name',
        'description',
        'category',
        'default_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'crm_service_id');
    }

    public function customerServices(): HasMany
    {
        return $this->hasMany(CrmCustomerService::class, 'crm_service_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Quotations/invoices only accept real Product line items. Rather than
     * fork that pipeline for CRM services, each service gets a lazily
     * created, non-stock-tracked shadow Product the first time it's quoted,
     * so a CRM quotation is an ordinary quotation with nothing CRM-specific
     * about it afterward. The shadow product is a normal, active product —
     * it will show up anywhere products are listed or searched.
     */
    public function ensureProduct(): Product
    {
        if ($this->product_id) {
            return $this->product;
        }

        $companyId = $this->company_id ?? Auth::user()?->company_id;

        $category = Category::query()
            ->where('company_id', $companyId)
            ->where('name', 'Services')
            ->first() ?? Category::create([
                'company_id' => $companyId,
                'name' => 'Services',
                'is_active' => true,
            ]);

        $unitOfMeasure = UnitOfMeasure::query()
            ->where('company_id', $companyId)
            ->where('name', 'Service')
            ->first() ?? UnitOfMeasure::create([
                'company_id' => $companyId,
                'name' => 'Service',
                'abbreviation' => 'svc',
            ]);

        $product = Product::create([
            'company_id' => $companyId,
            'category_id' => $category->id,
            'name' => $this->name,
            'sku' => 'SVC-'.$this->id,
            'description' => $this->description,
            'unit_of_measure_id' => $unitOfMeasure->id,
            'cost_price' => 0,
            'selling_price' => $this->default_price,
            'is_active' => true,
            'track_inventory' => false,
        ]);

        $this->update(['product_id' => $product->id]);

        return $product;
    }
}
