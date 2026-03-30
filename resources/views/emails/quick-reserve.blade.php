<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Reservation</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0 0 8px; font-size: 22px; }
        .header p { margin: 0; opacity: .8; font-size: 14px; }
        .body { padding: 28px 24px; }
        .info-card { background: #f8f9fa; border-left: 4px solid #1a1a2e; border-radius: 4px; padding: 16px; margin: 16px 0; }
        .info-row { display: flex; justify-content: space-between; margin: 6px 0; font-size: 14px; }
        .label { color: #666; }
        .value { font-weight: bold; }
        .timer-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 14px 16px; text-align: center; margin: 20px 0; }
        .timer-box strong { font-size: 16px; color: #856404; }
        .cta-btn {
            display: block; width: fit-content; margin: 24px auto;
            background: #1a1a2e; color: #fff; text-decoration: none;
            padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: bold;
        }
        .note { font-size: 12px; color: #999; margin-top: 4px; text-align: center; }
        .footer { background: #f4f4f4; text-align: center; padding: 16px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🎟️ Ticket Reserved!</h1>
        <p>Complete payment within <strong>10 minutes</strong> to secure your spot.</p>
    </div>
    <div class="body">
        <p>Hi <strong>{{ $reservation->email }}</strong>,</p>
        <p>Your ticket has been reserved. Please complete your payment before the reservation expires.</p>

        <div class="info-card">
            <div class="info-row">
                <span class="label">Ticket</span>
                <span class="value">{{ $ticket->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Event</span>
                <span class="value">{{ $ticket->event->name ?? 'Concert' }}</span>
            </div>
            @if($ticket->event->venue ?? null)
            <div class="info-row">
                <span class="label">Venue</span>
                <span class="value">{{ $ticket->event->venue }}</span>
            </div>
            @endif
            @if($ticket->event->event_date ?? null)
            <div class="info-row">
                <span class="label">Date</span>
                <span class="value">{{ \Carbon\Carbon::parse($ticket->event->event_date)->format('F j, Y') }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="label">Price</span>
                <span class="value">₱{{ number_format($ticket->price, 2) }}</span>
            </div>
        </div>

        <div class="timer-box">
            ⏳ <strong>Reservation expires at {{ $reservation->expires_at->format('g:i A') }} ({{ $reservation->expires_at->format('M j, Y') }})</strong>
            <br>
            <small style="color:#856404;">After this time your reserved spot will be released.</small>
        </div>

        <a href="{{ url('/pay/' . $reservation->token) }}" class="cta-btn">
            💳 Complete Payment Now
        </a>
        <p class="note">
            This link is valid for this reservation only.<br>
            Do not share this link with others.
        </p>

        <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
        <p style="font-size:13px;color:#666;">
            To complete your payment, click the button above and upload your GCash, bank transfer, or PayMaya receipt.
            Your e-ticket will be emailed once your payment is verified by our team.
        </p>

        @if($reservation->want_register)
        <p style="font-size:13px;color:#555;background:#e8f4fd;padding:12px;border-radius:6px;">
            🎉 You opted to create an account. Once your payment is confirmed, we'll send you a link to set your password and access your account.
        </p>
        @endif
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} JJ Networks NDS. All rights reserved.
    </div>
</div>
</body>
</html>
