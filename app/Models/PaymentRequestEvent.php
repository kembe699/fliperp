<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in a payment request's timeline: who did what, when, and why. */
class PaymentRequestEvent extends Model
{
    protected $fillable = ['payment_request_id', 'user_id', 'action', 'from_status', 'to_status', 'comment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
