<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOtp extends Model
{
    protected $fillable = [
        'email',
        'token',
        'otp_hash',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
