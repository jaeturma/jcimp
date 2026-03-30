<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        // Ensure all needed relations are loaded
        $this->order->loadMissing(['items.ticket.event', 'issuedTickets.ticket.event']);
    }

    public function envelope(): Envelope
    {
        $eventName = $this->order->items->first()?->ticket?->event?->name ?? 'Event';

        return new Envelope(
            subject: "🎟️ Your Ticket is Confirmed — {$eventName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-issued',
            with: [
                'order'         => $this->order,
                'issuedTickets' => $this->order->issuedTickets,
                'items'         => $this->order->items,
                'event'         => $this->order->items->first()?->ticket?->event,
            ],
        );
    }
}
