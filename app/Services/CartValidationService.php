<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Validates a cart (collection of reservations) before converting to an order.
 *
 * Responsibilities:
 * - All reservations are active and match the checkout email
 * - Student ticket total across ALL variants ≤ 1
 * - Verified student status when cart contains a student ticket
 * - No prior paid student order for this email (cross-order duplicate guard)
 */
class CartValidationService
{
    /**
     * @param  Collection<int, Reservation>  $reservations  Pre-loaded with 'ticket' relation
     *
     * @throws RuntimeException
     */
    public function validate(Collection $reservations, string $email, ?User $user): void
    {
        $this->assertReservationsActive($reservations, $email);
        $this->assertStudentRules($reservations, $email, $user);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function assertReservationsActive(Collection $reservations, string $email): void
    {
        foreach ($reservations as $reservation) {
            if ($reservation->email !== $email) {
                throw new RuntimeException(
                    "Reservation #{$reservation->id} does not belong to the provided email."
                );
            }

            if ($reservation->isExpired()) {
                throw new RuntimeException(
                    "Your reservation for \"{$reservation->ticket?->name}\" has expired. Please start over."
                );
            }
        }
    }

    private function assertStudentRules(Collection $reservations, string $email, ?User $user): void
    {
        // Sum student-type ticket quantities across the entire cart
        $studentQty = $reservations
            ->filter(fn (Reservation $r) => $r->ticket->isStudent())
            ->sum('quantity');

        if ($studentQty === 0) {
            return; // No student tickets — nothing to validate
        }

        // Rule 1: Total student quantity across all variants must be exactly 1
        if ($studentQty > 1) {
            throw new RuntimeException(
                'Only 1 student ticket is allowed per order across all student ticket variants.'
            );
        }

        // Rule 2: Must have verified student status (logged-in user or approved guest)
        $isVerified = $user
            ? $user->canBuyStudentTicket()
            : StudentVerification::where('guest_email', $email)
                ->where('status', 'approved')
                ->exists();

        if (! $isVerified) {
            throw new RuntimeException(
                'Student tickets require verified student status. '
                . 'Please complete student verification before purchasing.'
            );
        }

        // Rule 3: No prior paid student ticket (any variant) for this email
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
