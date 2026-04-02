<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket is Confirmed</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #e8eaf0; padding: 28px 12px; color: #1a1a2e; }

        /* Outer wrapper */
        .wrap { max-width: 620px; margin: 0 auto; }

        /* Confirmation header */
        .conf-header { background: linear-gradient(135deg, #1a1a2e 0%, #2d3561 100%); color: #fff; border-radius: 10px 10px 0 0; padding: 32px; text-align: center; }
        .conf-header .check { font-size: 44px; display: block; margin-bottom: 10px; }
        .conf-header h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .conf-header p { font-size: 13px; opacity: .7; }

        /* Email body */
        .email-body { background: #fff; padding: 28px 32px; }
        .greeting { font-size: 15px; color: #444; margin-bottom: 22px; line-height: 1.6; }

        /* Event banner */
        .event-banner { border-left: 4px solid #2d3561; background: #f5f6fa; border-radius: 4px; padding: 14px 16px; margin-bottom: 28px; }
        .event-banner .ev-name { font-size: 17px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
        .event-banner .ev-meta { font-size: 13px; color: #666; }

        /* ── PHYSICAL TICKET CARD ─────────────────────────── */
        .ticket-wrap { margin-bottom: 28px; }

        .ticket { border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.13); border: 1.5px solid #d8dae8; }

        /* Cover photo */
        .ticket-cover { width: 100%; height: 140px; object-fit: cover; display: block; background: linear-gradient(135deg, #1a1a2e, #2d3561); }
        .ticket-cover-placeholder { width: 100%; height: 140px; background: linear-gradient(135deg, #1a1a2e 0%, #2d3561 60%, #4a5491 100%); display: flex; align-items: center; justify-content: center; }
        .ticket-cover-placeholder span { font-size: 48px; }

        /* Ticket body: two columns */
        .ticket-body { display: table; width: 100%; background: #fff; }
        .ticket-info { display: table-cell; padding: 20px 20px 20px 22px; vertical-align: top; width: 60%; }
        .ticket-qr-col { display: table-cell; vertical-align: middle; text-align: center; width: 40%; background: #fafbff; border-left: 2px dashed #dde1f0; padding: 18px 16px; }

        /* Ticket info fields */
        .ticket-name { font-size: 16px; font-weight: 800; color: #1a1a2e; margin-bottom: 2px; }
        .ticket-type { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; background: #1a1a2e; color: #fff; border-radius: 3px; padding: 2px 7px; margin-bottom: 12px; }
        .ticket-status { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; background: #d4edda; color: #155724; border-radius: 3px; padding: 2px 8px; margin-bottom: 14px; margin-left: 4px; }

        .field { margin-bottom: 9px; }
        .field-label { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: #999; margin-bottom: 2px; }
        .field-value { font-size: 13px; font-weight: 600; color: #222; }
        .field-value.mono { font-family: monospace; font-size: 11px; word-break: break-all; }

        /* Tear notches */
        .ticket-tear { position: relative; background: #e8eaf0; height: 0; }
        .ticket-tear::before,
        .ticket-tear::after {
            content: '';
            position: absolute;
            width: 20px; height: 20px;
            background: #e8eaf0;
            border-radius: 50%;
            top: -10px;
        }
        .ticket-tear::before { left: -11px; }
        .ticket-tear::after  { right: -11px; }

        /* QR section */
        .qr-img { display: block; margin: 0 auto 8px; border-radius: 8px; border: 1px solid #e8eaf0; padding: 6px; background: #fff; }
        .qr-hint { font-size: 10px; color: #aaa; text-align: center; }
        .qr-code-text { font-family: monospace; font-size: 8px; word-break: break-all; background: #f3f4f8; border-radius: 4px; padding: 6px 8px; color: #888; text-align: left; margin-top: 8px; line-height: 1.4; }

        /* Ticket footer strip */
        .ticket-footer { background: #1a1a2e; color: rgba(255,255,255,.5); font-size: 10px; letter-spacing: .8px; text-align: center; padding: 7px 16px; text-transform: uppercase; }

        /* Order summary */
        .summary { background: #f5f6fa; border-radius: 8px; padding: 16px 20px; margin-top: 4px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; color: #555; margin-bottom: 6px; }
        .summary-row.total { font-weight: 700; font-size: 15px; color: #1a1a2e; padding-top: 10px; border-top: 1px solid #dee0ea; margin-top: 4px; margin-bottom: 0; }

        .notice { margin-top: 22px; background: #fff8e1; border: 1px solid #ffc107; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #7d5900; line-height: 1.5; }
        .student-notice { margin-top: 16px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 16px 20px; font-size: 13px; color: #7d5900; line-height: 1.6; }
        .student-notice h3 { font-size: 14px; font-weight: 800; color: #7d5900; margin-bottom: 10px; }
        .student-notice ul { padding-left: 20px; margin: 8px 0; }
        .student-notice li { margin-bottom: 5px; }
        .student-notice .penalty { margin-top: 12px; padding: 10px 14px; background: #fde8e8; border: 1px solid #e74c3c; border-radius: 6px; color: #c0392b; font-weight: 700; font-size: 13px; }

        /* Footer */
        .email-footer { background: #e8eaf0; text-align: center; padding: 18px; font-size: 11px; color: #aaa; border-radius: 0 0 10px 10px; border-top: 1px solid #d8dae8; }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ── Confirmation header ── --}}
    <div class="conf-header">
        <span class="check">🎟️</span>
        <h1>Your Ticket is Confirmed!</h1>
        <p>Order #{{ $order->reference }}</p>
    </div>

    <div class="email-body">

        <p class="greeting">
            Hi <strong>{{ $order->email }}</strong>,<br>
            Your payment has been verified. Present the QR code on your ticket at the venue entrance.
        </p>

        {{-- Event info --}}
        @if($event)
        <div class="event-banner">
            <p class="ev-name">{{ $event->name }}</p>
            <p class="ev-meta">
                @if($event->venue)📍 {{ $event->venue }}@endif
                @if($event->venue && $event->event_date) &nbsp;·&nbsp; @endif
                @if($event->event_date)📅 {{ \Carbon\Carbon::parse($event->event_date)->format('F j, Y · g:i A') }}@endif
            </p>
        </div>
        @endif

        {{-- ── One physical ticket card per issued ticket ── --}}
        @foreach($issuedTickets as $issued)
        @php $ticketEvent = $issued->ticket?->event ?? $event; @endphp
        <div class="ticket-wrap">
            <div class="ticket">

                {{-- Banner: tier image takes priority, then event cover, then placeholder --}}
                @php
                    $bannerUrl = $issued->ticket?->ticket_image_url ?? $ticketEvent?->cover_url ?? null;
                    $bannerAlt = $issued->ticket?->name ?? $ticketEvent?->name ?? 'Event';
                @endphp
                @if($bannerUrl)
                    <img class="ticket-cover" src="{{ $bannerUrl }}" alt="{{ $bannerAlt }}" />
                @else
                    <div class="ticket-cover-placeholder"><span>🎪</span></div>
                @endif

                {{-- Tear notch strip --}}
                <div class="ticket-tear"></div>

                {{-- Two-column body: info | QR --}}
                <div class="ticket-body">

                    {{-- Left: ticket details --}}
                    <div class="ticket-info">
                        <p class="ticket-name">{{ $issued->ticket?->name ?? 'General Admission' }}</p>
                        <span class="ticket-type">{{ $issued->ticket?->type ?? 'general' }}</span>
                        <span class="ticket-status">✔ {{ $issued->status }}</span>

                        @if($ticketEvent)
                        <div class="field">
                            <p class="field-label">Event</p>
                            <p class="field-value">{{ $ticketEvent->name }}</p>
                        </div>
                        @endif

                        @if($ticketEvent?->event_date)
                        <div class="field">
                            <p class="field-label">Date &amp; Time</p>
                            <p class="field-value">{{ \Carbon\Carbon::parse($ticketEvent->event_date)->format('M j, Y · g:i A') }}</p>
                        </div>
                        @endif

                        @if($ticketEvent?->venue)
                        <div class="field">
                            <p class="field-label">Venue</p>
                            <p class="field-value">{{ $ticketEvent->venue }}</p>
                        </div>
                        @endif

                        @if($issued->holder_name)
                        <div class="field">
                            <p class="field-label">Ticket Holder</p>
                            <p class="field-value">{{ $issued->holder_name }}</p>
                        </div>
                        @endif

                        <div class="field">
                            <p class="field-label">Order Ref</p>
                            <p class="field-value mono">{{ $order->reference }}</p>
                        </div>
                    </div>

                    {{-- Right: QR code --}}
                    <div class="ticket-qr-col">
                        <img
                            class="qr-img"
                            src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=6&data={{ urlencode($issued->qr_code) }}"
                            alt="Ticket QR Code"
                            width="168"
                            height="168"
                        />
                        <p class="qr-hint">📷 Scan at entrance</p>
                        <pre class="qr-code-text">{{ $issued->qr_code }}</pre>
                    </div>

                </div>

                {{-- Footer strip --}}
                <div class="ticket-footer">
                    Single entry · {{ $order->reference }} · Do not share
                </div>

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
            <div class="summary-row total">
                <span>Total Paid</span>
                <span>₱{{ number_format($order->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="notice">
            ⚠️ <strong>Important:</strong> Each QR code is valid for <strong>single entry only</strong>.
            Do not share your QR code with others. Keep this email safe.
        </div>

        {{-- Student ID reminder — only shown when the order contains a student ticket --}}
        @if($items->contains(fn($item) => $item->ticket?->type === 'student'))
        <div class="student-notice">
            <h3>🎓 Student Ticket Holder — Required Action on Event Day</h3>
            <p>You purchased a <strong>Student Ticket</strong>. Please read the following carefully:</p>
            <ul>
                <li>You are required to bring your <strong>valid and non-expired</strong> school-issued Student ID.</li>
                <li>Your Student ID must clearly show the current <strong>School Year</strong>.</li>
                <li>An ID with no School Year indicated will <strong>not be accepted</strong>.</li>
                <li>Your ID will be checked at the venue entrance before admission.</li>
            </ul>
            <div class="penalty">
                ❗ Failure to present a valid Student ID at the venue will result in your ticket being
                converted to <strong>General Admission + ₱300 additional fee</strong> payable on-site.
            </div>
        </div>
        @endif

    </div>

    <div class="email-footer">
        &copy; {{ date('Y') }} JJ Networks NDS. All rights reserved.<br>
        This is an automated email — please do not reply.
    </div>

</div>
</body>
</html>
