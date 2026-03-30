<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Transferred to You</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 24px; }
        .ticket-card { border: 2px dashed #4f2bab; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .info-row { display: flex; justify-content: space-between; margin: 6px 0; font-size: 14px; }
        .label { color: #666; }
        .qr-code { font-family: monospace; font-size: 11px; word-break: break-all; background: #f0f0f0; padding: 8px; border-radius: 4px; margin-top: 8px; }
        .footer { background: #f4f4f4; text-align: center; padding: 16px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🎟️ A Ticket Has Been Transferred to You!</h1>
        <p>{{ $issued->ticket?->event?->name ?? 'Event Ticket' }}</p>
    </div>
    <div class="body">
        <p>Hello,</p>
        <p>Someone has transferred their ticket to your email address. Below are your ticket details.</p>

        <div class="ticket-card">
            <div class="info-row">
                <span class="label">Ticket</span>
                <span><strong>{{ $issued->ticket?->name ?? 'Concert Ticket' }}</strong></span>
            </div>
            <div class="info-row">
                <span class="label">Type</span>
                <span>{{ ucfirst($issued->ticket?->type ?? '') }}</span>
            </div>
            @if($issued->ticket?->event)
            <div class="info-row">
                <span class="label">Event</span>
                <span>{{ $issued->ticket->event->name }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="label">Order Reference</span>
                <span>{{ $issued->order?->reference }}</span>
            </div>
            <p style="font-size:12px;color:#666;margin-top:12px;">QR Code (present at entrance)</p>
            <div class="qr-code">{{ $issued->qr_code }}</div>
        </div>

        <p style="margin-top: 24px; font-size: 13px; color: #666;">
            Keep this email safe. You can view all your tickets at <a href="{{ url('/my-tickets') }}">{{ url('/my-tickets') }}</a>.
        </p>
        <p style="font-size: 13px; color: #666;">
            Do not share your QR code. Each code is valid for single entry only.
        </p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} JJ Networks NDS. All rights reserved.
    </div>
</div>
</body>
</html>
