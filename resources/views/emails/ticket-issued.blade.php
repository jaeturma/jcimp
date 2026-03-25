<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket is Confirmed</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 24px 12px; }
        .container { max-width: 620px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 36px 32px; text-align: center; }
        .header .check { font-size: 42px; margin-bottom: 10px; }
        .header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 700; }
        .header p { margin: 0; opacity: .75; font-size: 14px; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #333; margin-bottom: 20px; }
        .event-banner { background: #f8f9fa; border-left: 4px solid #1a1a2e; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px; }
        .event-banner .event-name { font-size: 18px; font-weight: 700; color: #1a1a2e; margin: 0 0 4px; }
        .event-banner .event-meta { font-size: 13px; color: #666; margin: 0; }
        .ticket-card { border: 2px dashed #d0d0d0; border-radius: 10px; overflow: hidden; margin-bottom: 20px; }
        .ticket-header { background: #1a1a2e; color: #fff; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; }
        .ticket-header .tname { font-size: 15px; font-weight: 700; }
        .ticket-header .tbadge { background: rgba(255,255,255,.2); border-radius: 4px; padding: 2px 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
        .ticket-body { padding: 20px; text-align: center; background: #fff; }
        .qr-image { display: block; margin: 0 auto 10px; border: 1px solid #eee; border-radius: 8px; padding: 8px; background: #fff; }
        .qr-label { font-size: 11px; color: #999; margin: 0 0 12px; }
        .qr-code-text { font-family: monospace; font-size: 9px; word-break: break-all; background: #f5f5f5; padding: 8px 10px; border-radius: 4px; color: #555; text-align: left; margin: 0; }
        .ticket-status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: .5px; background: #d4edda; color: #155724; margin-bottom: 14px; }
        .info-grid { display: table; width: 100%; font-size: 13px; border-top: 1px solid #eee; padding-top: 14px; margin-top: 14px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; color: #888; padding: 3px 12px 3px 0; white-space: nowrap; }
        .info-value { display: table-cell; font-weight: 600; color: #333; padding: 3px 0; }
        .summary { background: #f8f9fa; border-radius: 8px; padding: 16px 20px; margin-top: 24px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; color: #555; }
        .summary-row:last-child { margin-bottom: 0; font-weight: 700; font-size: 16px; color: #1a1a2e; padding-top: 10px; border-top: 1px solid #dee2e6; }
        .notice { margin-top: 24px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #856404; }
        .footer { background: #f0f2f5; text-align: center; padding: 20px; font-size: 11px; color: #aaa; border-top: 1px solid #e9ecef; }
    </style>
</head>
<body>
<div class="container">

    {{-- Header --}}
    <div class="header">
        <div class="check">✅</div>
        <h1>Your Ticket is Confirmed!</h1>
        <p>Order #{{ $order->reference }}</p>
    </div>

    <div class="body">

        <p class="greeting">
            Hi <strong>{{ $order->email }}</strong>,<br>
            Thank you! Your payment has been verified and your ticket is ready.
            Present the QR code below at the venue entrance.
        </p>

        {{-- Event info --}}
        @if($event)
        <div class="event-banner">
            <p class="event-name">{{ $event->name }}</p>
            <p class="event-meta">
                @if($event->venue)📍 {{ $event->venue }}@endif
                @if($event->venue && $event->event_date) &nbsp;·&nbsp; @endif
                @if($event->event_date)📅 {{ \Carbon\Carbon::parse($event->event_date)->format('F j, Y') }}@endif
            </p>
        </div>
        @endif

        {{-- One card per issued ticket --}}
        @foreach($issuedTickets as $issued)
        <div class="ticket-card">
            <div class="ticket-header">
                <span class="tname">{{ $issued->ticket?->name ?? 'Concert Ticket' }}</span>
                <span class="tbadge">{{ $issued->ticket?->type ?? 'general' }}</span>
            </div>
            <div class="ticket-body">
                <span class="ticket-status">✔ {{ strtoupper($issued->status) }}</span>

                {{-- QR Code image via free API --}}
                <img
                    class="qr-image"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($issued->qr_code) }}"
                    alt="QR Code"
                    width="200"
                    height="200"
                />
                <p class="qr-label">Scan this code at the entrance</p>

                {{-- Fallback text code --}}
                <pre class="qr-code-text">{{ $issued->qr_code }}</pre>

                @if($issued->holder_name)
                <div class="info-grid" style="margin-top:10px">
                    <div class="info-row">
                        <span class="info-label">Ticket Holder</span>
                        <span class="info-value">{{ $issued->holder_name }}</span>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach

        {{-- Order summary --}}
        <div class="summary">
            @foreach($items as $item)
            <div class="summary-row">
                <span>{{ $item->ticket?->name }} × {{ $item->quantity }}</span>
                <span>₱{{ number_format($item->price * $item->quantity, 2) }}</span>
            </div>
            @endforeach
            <div class="summary-row">
                <span>Total Paid</span>
                <span>₱{{ number_format($order->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="notice">
            ⚠️ <strong>Important:</strong> Each QR code is valid for single entry only.
            Do not share your QR code with others. Keep this email safe.
        </div>

    </div>

    <div class="footer">
        &copy; {{ date('Y') }} Concert Ticketing. All rights reserved.<br>
        This is an automated email — please do not reply.
    </div>

</div>
</body>
</html>
