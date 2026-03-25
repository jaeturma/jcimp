<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketIssued extends Model
{
    protected $table = 'tickets_issued';

    protected $fillable = [
        'order_id',
        'ticket_id',
        'qr_code',
        'status',
        'used_at',
        'holder_name',
        'holder_email',
        'transfer_token',
        'is_for_resale',
        'resale_price',
        'transferred_to_user_id',
    ];

    protected $casts = [
        'used_at'      => 'datetime',
        'is_for_resale' => 'boolean',
        'resale_price'  => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'valid');
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('status', 'used');
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    public function markUsed(): void
    {
        $this->update(['status' => 'used', 'used_at' => now()]);
    }
}
