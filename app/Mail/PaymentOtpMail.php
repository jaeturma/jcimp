<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly string $orderReference,
        public readonly ?string $ticketName = null,
        public readonly ?string $eventName = null,
        public readonly ?float  $price = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Payment OTP — Complete Your Ticket Purchase',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-otp',
        );
    }
}
