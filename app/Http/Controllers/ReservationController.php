<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReserveTicketRequest;
use App\Models\Reservation;
use App\Models\StudentVerification;
use App\Models\Ticket;
use App\Models\TicketIssued;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    /**
     * Reserve the full cart in one call.
     *
     * POST /api/cart/reserve
     * Body: { items: [{ticket_id, quantity}, ...], email }
     *
     * Each ticket gets its own Redis lock. If any item fails, all
     * reservations created in this call are rolled back.
     */
    public function reserveCart(Request $request): JsonResponse
    {
        $request->validate([
            'items'                  => ['required', 'array', 'min:1', 'max:10'],
            'items.*.ticket_id'      => ['required', 'integer', 'exists:tickets,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:10'],
            'email'                  => ['required', 'email', 'max:255'],
            'student_access_token'   => ['nullable', 'string'],
        ]);

        // If cart contains a student ticket, validate student verification
        $hasStudentTicket = collect($request->input('items'))->contains(function ($item) {
            return Ticket::find($item['ticket_id'])?->type === 'student';
        });

        if ($hasStudentTicket) {
            $user       = $request->user();
            $orderEmail = strtolower(trim($request->input('email')));

            // Logged-in user with verified student status — no token needed
            if ($user && $user->canBuyStudentTicket()) {
                // Check 1-ticket limit for this email
                if ($this->emailHasStudentTicket($orderEmail)) {
                    return response()->json(['message' => 'This email already has a student ticket. Only 1 student ticket is allowed per verified email.'], 422);
                }
            } else {
                // Guest or unverified user — require a valid student access token
                $token = $request->input('student_access_token');
                if (!$token) {
                    return response()->json(['message' => 'Student verification required. Please verify your student email first.'], 422);
                }
                $sv = StudentVerification::where('access_token', $token)
                    ->where('status', 'approved')
                    ->where('token_expires_at', '>', now())
                    ->first();
                if (!$sv) {
                    return response()->json(['message' => 'Student verification token is invalid or expired. Please re-verify your student email.'], 422);
                }
                // Check 1-ticket limit for both the order email and the verified student email
                if ($this->emailHasStudentTicket($orderEmail) || $this->emailHasStudentTicket($sv->guest_email)) {
                    return response()->json(['message' => 'This student email already has a student ticket. Only 1 student ticket is allowed per verified email.'], 422);
                }
            }
        }

        try {
            $reservations = $this->reservationService->reserveTickets(
                cartItems: $request->input('items'),
                email:     $request->string('email')->value(),
                user:      $request->user(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // All reservations share the same expiry (created in the same call)
        $expiresAt = $reservations->min('expires_at');

        return response()->json([
            'reservations' => $reservations->map(fn ($r) => [
                'reservation_id' => $r->id,
                'ticket_id'      => $r->ticket_id,
                'quantity'       => $r->quantity,
                'expires_at'     => $r->expires_at->toISOString(),
            ]),
            'expires_at'   => $expiresAt->toISOString(),
            'seconds_left' => (int) now()->diffInSeconds($expiresAt),
        ], 201);
    }

    /**
     * Reserve a single ticket (legacy / single-item flow).
     *
     * POST /api/reservations
     */
    public function store(ReserveTicketRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationService->reserve(
                ticketId: $request->integer('ticket_id'),
                email:    $request->string('email')->value(),
                quantity: $request->integer('quantity'),
                user:     $request->user(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'reservation_id' => $reservation->id,
            'expires_at'     => $reservation->expires_at->toISOString(),
            'seconds_left'   => (int) now()->diffInSeconds($reservation->expires_at),
        ], 201);
    }

    /**
     * Cancel / release a single reservation.
     *
     * DELETE /api/reservations/{reservation}
     */
    public function destroy(Reservation $reservation): JsonResponse
    {
        $this->reservationService->release($reservation);

        return response()->json(['message' => 'Reservation released.']);
    }

    private function emailHasStudentTicket(string $email): bool
    {
        return TicketIssued::whereHas('order', fn ($q) => $q->where('email', $email))
            ->whereHas('ticket', fn ($q) => $q->where('type', 'student'))
            ->whereIn('status', ['valid', 'used'])
            ->exists();
    }

    /**
     * Cancel all active reservations for an email (abandon cart).
     *
     * DELETE /api/cart/reserve
     * Body: { email }
     */
    public function cancelCart(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $reservations = Reservation::where('email', $request->string('email')->value())
            ->where('expires_at', '>', now())
            ->get();

        foreach ($reservations as $reservation) {
            $this->reservationService->release($reservation);
        }

        return response()->json(['message' => 'Cart cancelled.', 'released' => $reservations->count()]);
    }
}
