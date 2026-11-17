<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One approver in a rule's sequence: anyone with role_id, or the one user_id. */
class PaymentApprovalRuleStep extends Model
{
    protected $fillable = ['payment_approval_rule_id', 'sequence', 'label', 'role_id', 'user_id'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
