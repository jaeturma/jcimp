<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'name',
        'description',
        'venue',
        'event_date',
        'is_active',
        'cover_image',
    ];

    protected $appends = ['cover_url'];

    protected $casts = [
        'event_date' => 'datetime',
        'is_active'  => 'boolean',
    ];

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover_image) return null;
        return asset('storage/' . $this->cover_image);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Only tickets that have remaining capacity */
    public function activeTickets(): HasMany
    {
        return $this->hasMany(Ticket::class)
            ->whereRaw('total_quantity - reserved_quantity - sold_quantity > 0');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>', now())->orderBy('event_date');
    }
}
