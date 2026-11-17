<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A supporting document on a payment request (quote, invoice, receipt). */
class PaymentRequestAttachment extends Model
{
    protected $fillable = ['payment_request_id', 'file_path', 'file_name', 'file_size', 'mime_type', 'uploaded_by'];

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
