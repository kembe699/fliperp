<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An amount band (in the base currency) and the approval steps for payment
 * requests in it: applies to amounts above min_amount, up to and including
 * max_amount (no max_amount = no upper limit).
 */
class PaymentApprovalRule extends TenantModel
{
    use Auditable;

    protected $fillable = ['company_id', 'name', 'min_amount', 'max_amount', 'is_active'];

    protected function casts(): array
    {
        return ['min_amount' => 'decimal:2', 'max_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function covers(float $baseAmount): bool
    {
        return $baseAmount > (float) $this->min_amount
            && ($this->max_amount === null || $baseAmount <= (float) $this->max_amount);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PaymentApprovalRuleStep::class)->orderBy('sequence');
    }
}
