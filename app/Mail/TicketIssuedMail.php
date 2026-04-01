<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
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

    /** @return Attachment[] */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->order->issuedTickets as $index => $issued) {
            if (! $issued->ticket_card_path) continue;
            if (! Storage::disk('public')->exists($issued->ticket_card_path)) continue;

            $ticketName = $issued->ticket?->name ?? 'Ticket';
            $label      = \Illuminate\Support\Str::slug($ticketName) . '-' . ($index + 1) . '.jpg';

            $attachments[] = Attachment::fromStorageDisk('public', $issued->ticket_card_path)
                ->as($label)
                ->withMime('image/jpeg');
        }

        return $attachments;
    }
}
