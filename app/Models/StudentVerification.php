<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentVerification extends Model
{
    protected $fillable = [
        'user_id',
        'guest_email',
        'student_type',
        'school_email',
        'lrn_number',
        'student_id_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'otp_hash',
        'otp_expires_at',
        'otp_verified_at',
        'access_token',
        'token_expires_at',
    ];

    protected $casts = [
        'reviewed_at'    => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_verified_at'=> 'datetime',
        'token_expires_at'=> 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function isGuest(): bool { return $this->user_id === null; }

    public function displayEmail(): string
    {
        return $this->guest_email ?? $this->user?->email ?? '';
    }
}
