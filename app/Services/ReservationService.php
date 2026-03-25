<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationService
{
    // Lock TTL slightly longer than reservation window to avoid race on cleanup
    private const LOCK_TTL           = 15;
    private const RESERVATION_MINUTES = 10;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Reserve all items in a cart atomically.
     *
     * Each ticket gets its own Redis lock. If any item fails, all
     * previously-created reservations in this batch are rolled back.
     *
     * @param  array<int, array{ticket_id: int, quantity: int}>  $cartItems
     * @return Collection<int, Reservation>
     *
     * @throws RuntimeException
     */
    public function reserveTickets(array $cartItems, string $email, ?User $user = null): Collection
    {
        $created = collect();

        try {
            foreach ($cartItems as $item) {
                $created->push(
                    $this->reserve(
                        ticketId: (int) $item['ticket_id'],
                        email:    $email,
                        quantity: (int) $item['quantity'],
                        user:     $user,
                    )
                );
            }
        } catch (RuntimeException $e) {
            // Roll back all reservations that succeeded before the failure
            foreach ($created as $reservation) {
                $this->release($reservation);
            }
            throw $e;
        }

        return $created;
    }

    /**
     * Reserve a single ticket atomically (used internally by reserveTickets).
     *
     * @throws RuntimeException
     */
    public function reserve(int $ticketId, string $email, int $quantity, ?User $user = null): Reservation
    {
        $lock = Cache::lock("ticket_lock:{$ticketId}", self::LOCK_TTL);

        if (! $lock->get()) {
            throw new RuntimeException('System is busy, please try again.');
        }

        try {
            return DB::transaction(function () use ($ticketId, $email, $quantity, $user) {
                $ticket = Ticket::lockForUpdate()->findOrFail($ticketId);

                $this->validateStudentRules($ticket, $email, $quantity, $user);
                $this->ensureAvailability($ticket, $quantity);

                $ticket->increment('reserved_quantity', $quantity);

                return Reservation::create([
                    'ticket_id'  => $ticketId,
                    'email'      => $email,
                    'quantity'   => $quantity,
                    'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                ]);
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Release a reservation and restore the reserved_quantity.
     */
    public function release(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $ticket = Ticket::lockForUpdate()->find($reservation->ticket_id);

            if ($ticket) {
                $ticket->decrement('reserved_quantity', $reservation->quantity);
            }

            $reservation->delete();
        });
    }

    /**
     * Expire all past-due reservations (runs every minute via scheduler).
     */
    public function expireStale(): int
    {
        $expired = Reservation::expired()->with('ticket')->get();
        $count   = 0;

        foreach ($expired as $reservation) {
            $this->release($reservation);
            $count++;
        }

        return $count;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function ensureAvailability(Ticket $ticket, int $quantity): void
    {
        $available = $ticket->availableQuantity();

        if ($available < $quantity) {
            throw new RuntimeException(
                "Only {$available} ticket(s) remaining for \"{$ticket->name}\". Please reduce your quantity."
            );
        }
    }

    private function validateStudentRules(Ticket $ticket, string $email, int $quantity, ?User $user): void
    {
        if (! $ticket->isStudent()) {
            // Non-student: enforce per-user quantity cap
            $maxQty = $user ? 10 : ($ticket->max_per_user ?? 4);
            if ($quantity > $maxQty) {
                throw new RuntimeException(
                    "Maximum {$maxQty} ticket(s) per person for \"{$ticket->name}\"."
                );
            }
            return;
        }

        // Per-reservation cap — student tickets are always qty = 1
        if ($quantity > 1) {
            throw new RuntimeException('Student tickets are limited to 1 per reservation.');
        }

        // Must be a verified student to hold inventory
        if (! $user || ! $user->canBuyStudentTicket()) {
            throw new RuntimeException(
                'Student tickets require verified student status. '
                . 'Please complete student verification before purchasing.'
            );
        }

        // Early guard: block if a paid student ticket already exists for this email.
        // CartValidationService enforces the cross-variant version again at checkout.
        $alreadyOwns = Order::where('email', $email)
            ->where('status', 'paid')
            ->whereHas('items.ticket', fn ($q) => $q->where('type', 'student'))
            ->exists();

        if ($alreadyOwns) {
            throw new RuntimeException(
                'You have already purchased a student ticket. Only one is allowed per person.'
            );
        }
    }
}
