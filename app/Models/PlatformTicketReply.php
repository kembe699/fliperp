<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformTicketReply extends Model
{
    protected $fillable = [
        'ticket_id',
        'author_id',
        'body',
        'is_internal_note',
    ];

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PlatformTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
