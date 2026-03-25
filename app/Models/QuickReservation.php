<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickReservation extends Model
{
    protected $fillable = [
        'token',
        'ticket_id',
        'email',
        'want_register',
        'expires_at',
        'used_at',
        'otp_hash',
        'otp_expires_at',
        'otp_verified_at',
    ];

    protected $casts = [
        'want_register'   => 'boolean',
        'expires_at'      => 'datetime',
        'used_at'         => 'datetime',
        'otp_expires_at'  => 'datetime',
        'otp_verified_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
