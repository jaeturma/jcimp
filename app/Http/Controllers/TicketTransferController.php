<?php

namespace App\Http\Controllers;

use App\Models\TicketIssued;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketTransferMail;

class TicketTransferController extends Controller
{
    /**
     * GET /api/my-issued-tickets
     * Return all issued tickets for an email (for registered users, also by user_id).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $tickets = TicketIssued::with(['ticket.event', 'order'])
            ->whereHas('order', fn ($q) => $q->where('email', $request->email)->where('status', 'paid'))
            ->orWhere('holder_email', $request->email)
            ->get()
            ->map(fn ($t) => $this->formatTicket($t));

        return response()->json(['tickets' => $tickets]);
    }

    /**
     * PATCH /api/tickets-issued/{qr}/assign
     * Assign a holder name to a valid ticket.
     * Auth required if the ticket belongs to a registered user's order.
     */
    public function assign(Request $request, string $qr): JsonResponse
    {
        $ticket = TicketIssued::with(['order', 'ticket'])
            ->where('qr_code', $qr)
            ->firstOrFail();

        $request->validate([
            'holder_name'  => 'required|string|max:100',
            'holder_email' => 'nullable|email|max:255',
        ]);

        if (! $this->canManage($request, $ticket)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($ticket->isUsed()) {
            return response()->json(['message' => 'This ticket has already been used.'], 422);
        }

        $ticket->update([
            'holder_name'  => $request->holder_name,
            'holder_email' => $request->holder_email ?? $ticket->holder_email,
        ]);

        return response()->json(['message' => 'Holder assigned.', 'ticket' => $this->formatTicket($ticket->fresh())]);
    }

    /**
     * POST /api/tickets-issued/{qr}/transfer
     * Transfer a ticket to another email. Sends transfer email with a claim link.
     */
    public function transfer(Request $request, string $qr): JsonResponse
    {
        $ticket = TicketIssued::with(['order', 'ticket.event'])
            ->where('qr_code', $qr)
            ->firstOrFail();

        $request->validate([
            'to_email' => 'required|email|max:255|different:' . ($ticket->order->email ?? 'noreply@example.com'),
        ]);

        if (! $this->canManage($request, $ticket)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($ticket->isUsed()) {
            return response()->json(['message' => 'This ticket has already been used.'], 422);
        }

        if ($ticket->is_for_resale) {
            return response()->json(['message' => 'Cancel resale listing before transferring.'], 422);
        }

        $token = Str::random(40);

        $ticket->update([
            'transfer_token' => $token,
            'holder_email'   => $request->to_email,
        ]);

        // Send transfer notification email
        try {
            Mail::to($request->to_email)->send(new TicketTransferMail($ticket->fresh(['ticket.event', 'order']), $token));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('TicketTransfer email failed: ' . $e->getMessage());
        }

        return response()->json(['message' => "Transfer initiated. An email has been sent to {$request->to_email}."]);
    }

    /**
     * POST /api/tickets-issued/{qr}/resell
     * List a ticket for resale at the given price.
     */
    public function listResale(Request $request, string $qr): JsonResponse
    {
        $ticket = TicketIssued::with(['order', 'ticket'])
            ->where('qr_code', $qr)
            ->firstOrFail();

        $request->validate([
            'resale_price' => 'required|numeric|min:0|max:100000',
        ]);

        if (! $this->canManage($request, $ticket)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($ticket->isUsed()) {
            return response()->json(['message' => 'This ticket has already been used.'], 422);
        }

        $ticket->update([
            'is_for_resale' => true,
            'resale_price'  => $request->resale_price,
        ]);

        return response()->json(['message' => 'Ticket listed for resale.', 'ticket' => $this->formatTicket($ticket->fresh())]);
    }

    /**
     * DELETE /api/tickets-issued/{qr}/resell
     * Cancel a resale listing.
     */
    public function cancelResale(Request $request, string $qr): JsonResponse
    {
        $ticket = TicketIssued::with('order')
            ->where('qr_code', $qr)
            ->firstOrFail();

        if (! $this->canManage($request, $ticket)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $ticket->update([
            'is_for_resale' => false,
            'resale_price'  => null,
        ]);

        return response()->json(['message' => 'Resale listing cancelled.']);
    }

    /**
     * GET /api/resale-tickets
     * Public: list all tickets currently for resale.
     */
    public function resaleMarket(Request $request): JsonResponse
    {
        $tickets = TicketIssued::with(['ticket.event'])
            ->where('is_for_resale', true)
            ->where('status', 'valid')
            ->get()
            ->map(fn ($t) => [
                'qr_code'      => $t->qr_code,
                'ticket_name'  => $t->ticket?->name,
                'ticket_type'  => $t->ticket?->type,
                'event_name'   => $t->ticket?->event?->name,
                'resale_price' => (float) $t->resale_price,
                'holder_name'  => $t->holder_name,
            ]);

        return response()->json(['tickets' => $tickets]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check if the request can manage this ticket.
     * The ticket belongs to the requester if:
     *   - request provides the order email, OR
     *   - authenticated user owns the order, OR
     *   - the ticket's holder_email matches the request email param
     */
    private function canManage(Request $request, TicketIssued $ticket): bool
    {
        $email = $request->input('owner_email') ?? $request->user()?->email;

        if (! $email) return false;

        return $ticket->order->email === $email
            || $ticket->holder_email === $email
            || ($request->user() && $request->user()->email === $ticket->order->email);
    }

    private function formatTicket(TicketIssued $t): array
    {
        return [
            'id'            => $t->id,
            'qr_code'       => $t->qr_code,
            'status'        => $t->status,
            'holder_name'   => $t->holder_name,
            'holder_email'  => $t->holder_email,
            'is_for_resale' => $t->is_for_resale,
            'resale_price'  => $t->resale_price ? (float) $t->resale_price : null,
            'ticket_name'   => $t->ticket?->name,
            'ticket_type'   => $t->ticket?->type,
            'event_name'    => $t->ticket?->event?->name,
            'order_ref'     => $t->order?->reference,
            'used_at'       => $t->used_at?->toISOString(),
        ];
    }
}
