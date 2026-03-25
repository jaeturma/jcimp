<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyTicketsController extends Controller
{
    /**
     * GET /api/my-tickets?email=xxx
     * Returns orders + pending reservations for the given email.
     * Works for guests (email param) and authenticated users (uses auth email if no param).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required_without:use_auth', 'email', 'max:255'],
        ]);

        $email = $request->string('email')->value()
            ?: $request->user()?->email;

        if (!$email) {
            return response()->json(['message' => 'Email is required.'], 422);
        }

        // All orders for this email (newest first)
        $orders = Order::with(['items.ticket', 'issuedTickets.ticket'])
            ->where('email', $email)
            ->latest()
            ->get()
            ->map(function (Order $order) {
                $data = [
                    'id'             => $order->id,
                    'reference'      => $order->reference,
                    'email'          => $order->email,
                    'status'         => $order->status,
                    'payment_method' => $order->payment_method,
                    'total_amount'   => (float) $order->total_amount,
                    'created_at'     => $order->created_at->toISOString(),
                    'items'          => $order->items->map(fn($item) => [
                        'ticket_name' => $item->ticket?->name,
                        'ticket_type' => $item->ticket?->type,
                        'quantity'    => $item->quantity,
                        'price'       => (float) $item->price,
                    ]),
                    'issued_tickets' => [],
                ];

                if ($order->isPaid()) {
                    $data['issued_tickets'] = $order->issuedTickets->map(fn($t) => [
                        'qr_code'     => $t->qr_code,
                        'status'      => $t->status,
                        'ticket_name' => $t->ticket?->name,
                        'ticket_type' => $t->ticket?->type,
                    ]);
                }

                return $data;
            });

        // Active (unexpired) reservations treated as "cart" items
        $reservations = Reservation::with('ticket')
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->get()
            ->map(fn($r) => [
                'ticket_id'   => $r->ticket_id,
                'ticket_name' => $r->ticket?->name,
                'ticket_type' => $r->ticket?->type,
                'quantity'    => $r->quantity,
                'price'       => (float) ($r->ticket?->price ?? 0),
                'expires_at'  => $r->expires_at->toISOString(),
            ]);

        return response()->json([
            'email'        => $email,
            'orders'       => $orders,
            'reservations' => $reservations,
        ]);
    }
}
