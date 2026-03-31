<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TicketIssued;
use Illuminate\Support\Str;

class TicketGenerationService
{
    public function __construct(private readonly TicketCardService $cardService) {}

    /**
     * Generate QR-coded tickets for a paid order.
     * Creates one TicketIssued record per seat.
     */
    public function generate(Order $order): void
    {
        // Eager-load ticket → event so TicketCardService has what it needs
        $order->loadMissing(['items.ticket.event']);

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $qrCode = $this->generateQrCode($order, $item->ticket_id);

                $issued = TicketIssued::create([
                    'order_id'  => $order->id,
                    'ticket_id' => $item->ticket_id,
                    'qr_code'   => $qrCode,
                    'status'    => 'valid',
                ]);

                // Pre-load ticket relation (already loaded on $item)
                $issued->setRelation('ticket', $item->ticket);

                try {
                    $cardPath = $this->cardService->generate($issued, $order);
                    $issued->update(['ticket_card_path' => $cardPath]);
                } catch (\Throwable $e) {
                    // Non-fatal: ticket still works without card image
                    logger()->warning('TicketCardService failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Build a signed, unique QR code string for a ticket seat.
     */
    private function generateQrCode(Order $order, int $ticketId): string
    {
        $payload = implode('|', [
            $order->reference,
            $ticketId,
            Str::uuid()->toString(),
            now()->timestamp,
        ]);

        return hash_hmac('sha256', $payload, config('app.key'));
    }
}
