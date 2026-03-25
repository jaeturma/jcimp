<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * List available ticket tiers for an event.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['event_id' => ['required', 'integer', 'exists:events,id']]);

        $tickets = Ticket::where('event_id', $request->event_id)
            ->select([
                'id', 'name', 'price', 'type', 'max_per_user',
                'total_quantity', 'reserved_quantity', 'sold_quantity',
                'requires_verification',
            ])
            ->get()
            ->map(fn (Ticket $t) => [
                ...$t->toArray(),
                'available' => $t->availableQuantity(),
                'sold_out'  => $t->availableQuantity() === 0,
            ]);

        return response()->json(['tickets' => $tickets]);
    }

    /**
     * Show a single ticket tier.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json([
            'ticket'    => $ticket->only(['id', 'name', 'price', 'type', 'max_per_user', 'requires_verification']),
            'available' => $ticket->availableQuantity(),
        ]);
    }
}
