<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Orchestrates the full checkout pipeline:
 *
 *  1. Validate cart items (existence, quantity bounds)
 *  2. Validate student ticket rules (CartValidationService)
 *  3. Verify active reservations exist and match the cart
 *  4. Create order + order items (PaymentService)
 *
 * Returns payment-ready response data.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartValidationService $cartValidation,
        private readonly PaymentService        $paymentService,
    ) {}

    /**
     * Validate and convert a cart into a pending order.
     *
     * @param  array<int, array{ticket_id: int, quantity: int}>  $cartItems
     * @return array{order_id: int, order_reference: string, amount: float, payment_method: string}
     *
     * @throws RuntimeException
     */
    public function checkout(
        array   $cartItems,
        string  $email,
        string  $paymentMethod,
        ?User   $user = null,
    ): array {
        // ── Step 1: Validate cart inputs ──────────────────────────────────────
        $this->validateCartItems($cartItems);

        // ── Step 2: Find active reservations for this email ───────────────────
        $ticketIds    = array_column($cartItems, 'ticket_id');
        $reservations = Reservation::with('ticket')
            ->active()
            ->where('email', $email)
            ->whereIn('ticket_id', $ticketIds)
            ->get();

        // ── Step 3: Verify every cart item has a valid, matching reservation ──
        $this->verifyReservationsCoverCart($reservations, $cartItems);

        // ── Step 4: Cart-level validation (student rules, cross-variant limit) ─
        $this->cartValidation->validate($reservations, $email, $user);

        // ── Step 5: Create the order (DB transaction) ─────────────────────────
        $order = $this->paymentService->createOrder($reservations, $email, $paymentMethod);

        return [
            'order_id'        => $order->id,
            'order_reference' => $order->reference,
            'amount'          => (float) $order->total_amount,
            'payment_method'  => $order->payment_method,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Validate raw cart item data (before touching the database).
     *
     * @throws RuntimeException
     */
    private function validateCartItems(array $cartItems): void
    {
        if (empty($cartItems)) {
            throw new RuntimeException('Cart is empty.');
        }

        $seenTicketIds = [];

        foreach ($cartItems as $index => $item) {
            $ticketId = (int) ($item['ticket_id'] ?? 0);
            $quantity = (int) ($item['quantity']  ?? 0);

            if ($ticketId <= 0) {
                throw new RuntimeException("Invalid ticket ID at item #{$index}.");
            }

            if ($quantity <= 0) {
                throw new RuntimeException('Quantity must be greater than zero.');
            }

            if ($quantity > 10) {
                throw new RuntimeException('Quantity cannot exceed 10 per ticket tier.');
            }

            if (in_array($ticketId, $seenTicketIds, true)) {
                throw new RuntimeException('Duplicate ticket IDs in cart. Each ticket tier can only appear once.');
            }

            $seenTicketIds[] = $ticketId;

            // Verify the ticket actually exists
            $ticket = Ticket::find($ticketId);
            if (! $ticket) {
                throw new RuntimeException("Ticket #{$ticketId} does not exist.");
            }

            // Sanitize: quantity must be positive integer, no negative values
            if ($quantity < 1) {
                throw new RuntimeException("Quantity for \"{$ticket->name}\" must be at least 1.");
            }
        }
    }

    /**
     * Ensure every cart item has an active reservation with a matching quantity.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @param  array<int, array{ticket_id: int, quantity: int}>  $cartItems
     *
     * @throws RuntimeException
     */
    private function verifyReservationsCoverCart(Collection $reservations, array $cartItems): void
    {
        foreach ($cartItems as $item) {
            $ticketId    = (int) $item['ticket_id'];
            $quantity    = (int) $item['quantity'];
            $reservation = $reservations->firstWhere('ticket_id', $ticketId);

            if (! $reservation) {
                $ticket = Ticket::find($ticketId);
                throw new RuntimeException(
                    "No active reservation found for \"{$ticket?->name}\". "
                    . 'Please reserve your tickets again.'
                );
            }

            if ($reservation->isExpired()) {
                throw new RuntimeException(
                    "Your reservation for \"{$reservation->ticket?->name}\" has expired. "
                    . 'Please start over.'
                );
            }

            if ($reservation->quantity !== $quantity) {
                throw new RuntimeException(
                    "Cart quantity ({$quantity}) does not match your reservation "
                    . "({$reservation->quantity}) for \"{$reservation->ticket?->name}\". "
                    . 'Please start over.'
                );
            }
        }
    }
}
