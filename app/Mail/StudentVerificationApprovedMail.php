<?php
namespace App\Mail;

use App\Models\StudentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentVerificationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly StudentVerification $verification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Student Verification Approved — You Can Now Buy Your Ticket');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-approved');
    }
}
