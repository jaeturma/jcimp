<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'reference',
        'email',
        'status',
        'payment_method',
        'total_amount',
        'gateway_reference',
        'student_id_path',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function manualPayment(): HasOne
    {
        return $this->hasOne(ManualPayment::class);
    }

    public function issuedTickets(): HasMany
    {
        return $this->hasMany(TicketIssued::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'pending_verification']);
    }

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'pending_verification']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * True if this order contains at least one student-type ticket.
     */
    public function hasStudentTickets(): bool
    {
        return $this->items->contains(fn ($item) => $item->ticket?->isStudent());
    }

    /**
     * Generate a unique order reference, retrying on the rare collision.
     */
    public static function generateReference(): string
    {
        $attempts = 0;

        do {
            $reference = strtoupper('TKT-' . now()->format('Ymd') . '-' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));
            $exists    = static::where('reference', $reference)->exists();
            $attempts++;
        } while ($exists && $attempts < 10);

        return $reference;
    }
}
