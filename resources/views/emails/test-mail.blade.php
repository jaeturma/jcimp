<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Test Email</title>
<style>
  body { margin:0; padding:0; background:#f4f4f7; font-family:Arial,sans-serif; color:#333; }
  .wrapper { max-width:560px; margin:40px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
  .header { background:#1a1a2e; padding:32px 40px; text-align:center; }
  .header h1 { margin:0; color:#d4af37; font-size:22px; letter-spacing:1px; }
  .header p  { margin:6px 0 0; color:#aaa; font-size:13px; }
  .body { padding:32px 40px; }
  .body h2 { margin:0 0 12px; font-size:18px; color:#1a1a2e; }
  .body p  { margin:0 0 12px; font-size:14px; line-height:1.6; color:#555; }
  .badge { display:inline-block; background:#d4e8ff; color:#0055a5; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold; margin-bottom:20px; }
  .success-box { background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:16px 20px; margin:20px 0; }
  .success-box p { margin:0; color:#16a34a; font-size:14px; }
  .divider { border:none; border-top:1px solid #eee; margin:24px 0; }
  .meta { font-size:12px; color:#999; }
  .footer { background:#f4f4f7; padding:20px 40px; text-align:center; font-size:12px; color:#999; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>{{ config('app.name') }}</h1>
    <p>System Email Configuration Test</p>
  </div>
  <div class="body">
    <span class="badge">✓ SMTP Connected</span>
    <h2>Your email is working!</h2>
    <p>
      This is a test email sent from <strong>{{ config('app.name') }}</strong> to confirm
      that your SMTP configuration is set up correctly.
    </p>
    <div class="success-box">
      <p>✅ Email delivery is working. Your attendees will receive their tickets successfully.</p>
    </div>
    <hr class="divider" />
    <p class="meta">
      <strong>Sent at:</strong> {{ now()->format('F j, Y  g:i A') }}<br>
      <strong>From:</strong> {{ config('mail.from.name') }} &lt;{{ config('mail.from.address') }}&gt;<br>
      <strong>SMTP Host:</strong> {{ config('mail.mailers.smtp.host') }}:{{ config('mail.mailers.smtp.port') }}
    </p>
  </div>
  <div class="footer">
    {{ config('app.name') }} &mdash; Ticketing System &mdash; This is an automated test message.
  </div>
</div>
</body>
</html>
