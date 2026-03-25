<?php

namespace App\Mail;

use App\Models\TicketIssued;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketTransferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TicketIssued $issuedTicket,
        public readonly string       $token,
    ) {}

    public function envelope(): Envelope
    {
        $event = $this->issuedTicket->ticket?->event?->name ?? 'Event';
        return new Envelope(subject: "Ticket Transferred to You — {$event}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-transfer',
            with: [
                'issued' => $this->issuedTicket,
                'token'  => $this->token,
            ],
        );
    }
}
