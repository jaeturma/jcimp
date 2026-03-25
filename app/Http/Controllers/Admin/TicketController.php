<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTicketRequest;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    /**
     * List ticket tiers with filter/search/pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with('event')
            ->when($request->event_id, fn ($q) => $q->where('event_id', $request->event_id))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('type', 'like', '%'.$request->search.'%')
                    ->orWhereHas('event', fn ($q3) => $q3->where('name', 'like', '%'.$request->search.'%'));
            }));

        $perPage = intval($request->per_page) > 0 ? intval($request->per_page) : 10;
        $tickets = $query->paginate($perPage)->withQueryString();

        $tickets->transform(fn (Ticket $t) => [
            ...$t->toArray(),
            'available'    => $t->availableQuantity(),
            'revenue'      => number_format($t->sold_quantity * $t->price, 2),
            'event_name'   => $t->event?->name,
            'gcash_qr_url' => $t->gcash_qr ? Storage::disk('public')->url($t->gcash_qr) : null,
        ]);

        return response()->json($tickets);
    }

    /**
     * Create a new ticket tier.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = Ticket::create($request->validated());

        return response()->json(['ticket' => $ticket], 201);
    }

    /**
     * Update a ticket tier (name, price, quantity).
     */
    public function update(StoreTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket->update($request->validated());

        return response()->json(['ticket' => $ticket]);
    }

    /**
     * Delete a ticket tier (only if no orders placed).
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        if ($ticket->orderItems()->exists()) {
            return response()->json(['message' => 'Cannot delete a ticket tier with existing orders.'], 422);
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted.']);
    }

    /**
     * Upload or replace the GCash payment QR for a ticket tier.
     */
    public function uploadQr(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate(['qr_image' => 'required|file|image|max:4096']);

        if ($ticket->gcash_qr) {
            Storage::disk('public')->delete($ticket->gcash_qr);
        }

        $path = $request->file('qr_image')->store('gcash-qr', 'public');
        $ticket->update(['gcash_qr' => $path]);

        return response()->json([
            'message'      => 'GCash QR code updated.',
            'gcash_qr_url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Remove the GCash QR for a ticket tier.
     */
    public function removeQr(Ticket $ticket): JsonResponse
    {
        if ($ticket->gcash_qr) {
            Storage::disk('public')->delete($ticket->gcash_qr);
            $ticket->update(['gcash_qr' => null]);
        }

        return response()->json(['message' => 'GCash QR code removed.']);
    }

    /**
     * Sales summary for all tiers.
     */
    public function stats(): JsonResponse
    {
        $stats = Ticket::with('event')
            ->selectRaw('*, (sold_quantity * price) as revenue')
            ->get();

        return response()->json(['stats' => $stats]);
    }
}
