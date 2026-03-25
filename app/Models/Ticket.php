<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'price',
        'total_quantity',
        'reserved_quantity',
        'sold_quantity',
        'type',
        'max_per_user',
        'requires_verification',
        'gcash_qr',
    ];

    protected $casts = [
        'price'                 => 'decimal:2',
        'requires_verification' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function activeReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->where('expires_at', '>', now());
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function issuedTickets(): HasMany
    {
        return $this->hasMany(TicketIssued::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeStudent(Builder $query): Builder
    {
        return $query->where('type', 'student');
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('type', 'regular');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereRaw('total_quantity - reserved_quantity - sold_quantity > 0');
    }

    // ── Accessors / Business Logic ────────────────────────────────────────────

    public function isStudent(): bool
    {
        return $this->type === 'student';
    }

    public function isSoldOut(): bool
    {
        return $this->availableQuantity() === 0;
    }

    public function availableQuantity(): int
    {
        return max(0, $this->total_quantity - $this->reserved_quantity - $this->sold_quantity);
    }
}
