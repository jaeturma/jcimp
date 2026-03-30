<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Verification Approved</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 580px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 28px 24px; font-size: 15px; color: #333; }
        .cta { display: block; width: fit-content; margin: 24px auto; background: #1a1a2e; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: bold; font-size: 16px; }
        .footer { background: #f4f4f4; text-align: center; padding: 16px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header"><h1>Student Verification Approved!</h1></div>
    <div class="body">
        <p>Hi <strong>{{ $verification->displayEmail() }}</strong>,</p>
        <p>Great news! Your student verification has been <strong>approved</strong>. You can now proceed to purchase your student ticket.</p>
        <p>Click the button below to go back to the ticket selection page and complete your purchase. You will need to verify your email again with an OTP to get your access code.</p>
        <a href="{{ url('/tickets') }}" class="cta">Buy My Ticket Now</a>
        <p style="font-size:13px;color:#888;">This approval is valid for 24 hours from the time of approval.</p>
    </div>
    <div class="footer">&copy; {{ date('Y') }} JJ Networks NDS. All rights reserved.</div>
</div>
</body>
</html>
