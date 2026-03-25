<?php

namespace App\Mail;

use App\Models\QuickReservation;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuickReserveMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuickReservation $reservation,
        public readonly Ticket           $ticket,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Ticket Reservation – Complete Your Payment',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quick-reserve',
        );
    }
}
