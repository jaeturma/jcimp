<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TicketIssued;
use Illuminate\Support\Str;

class TicketGenerationService
{
    /**
     * Generate QR-coded tickets for a paid order.
     * Creates one TicketIssued record per seat.
     */
    public function generate(Order $order): void
    {
        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $qrCode = $this->generateQrCode($order, $item->ticket_id);

                TicketIssued::create([
                    'order_id'  => $order->id,
                    'ticket_id' => $item->ticket_id,
                    'qr_code'   => $qrCode,
                    'status'    => 'valid',
                ]);
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
