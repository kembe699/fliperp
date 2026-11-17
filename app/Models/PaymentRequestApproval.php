<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One approval step of one round of a payment request (copied from the rule at submission). */
class PaymentRequestApproval extends Model
{
    protected $fillable = [
        'payment_request_id', 'round', 'sequence', 'label', 'role_id', 'user_id',
        'status', 'decided_by', 'decided_at', 'comment',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** Whether $user is the kind of approver this step asks for (by role, or the named user). */
    public function isFor(User $user): bool
    {
        return $this->user_id
            ? $this->user_id === $user->id
            : $user->roles->contains('id', $this->role_id);
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
