<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable Two-Factor Authentication</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a1a; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px 30px; }
        .body p { color: #444444; line-height: 1.6; margin: 0 0 16px; }
        .alert { background: #fff8e1; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 0 4px 4px 0; margin: 24px 0; }
        .alert p { color: #92400e; margin: 0; font-size: 14px; line-height: 1.6; }
        .steps { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .steps h3 { margin: 0 0 12px; color: #1e40af; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
        .steps ol { margin: 0; padding-left: 20px; color: #1a1a1a; }
        .steps ol li { margin-bottom: 8px; font-size: 14px; line-height: 1.5; }
        .btn { display: inline-block; margin: 24px 0; padding: 14px 32px; background: #e63946; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; }
        .footer { padding: 20px 30px; background: #f9f9f9; text-align: center; }
        .footer p { color: #999999; font-size: 12px; margin: 4px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Okelcor Admin Security</h1>
        </div>
        <div class="body">
            <p>Hello {{ $admin->first_name ?? $admin->name }},</p>
            <p>We noticed that your Okelcor admin account does not yet have two-factor authentication (2FA) enabled. 2FA adds a critical second layer of security to protect the admin panel and customer data.</p>

            @if ($graceUntil)
            <div class="alert">
                <p><strong>Action required by {{ $graceUntil }}.</strong> After this date, 2FA will be mandatory and you will not be able to access the admin panel without it enabled.</p>
            </div>
            @else
            <div class="alert">
                <p><strong>Please enable 2FA as soon as possible.</strong> Accounts without 2FA may be restricted from accessing sensitive admin functions.</p>
            </div>
            @endif

            <div class="steps">
                <h3>How to Enable 2FA</h3>
                <ol>
                    <li>Log in to the admin panel</li>
                    <li>Go to <strong>Security Settings</strong></li>
                    <li>Click <strong>Enable Two-Factor Authentication</strong></li>
                    <li>Scan the QR code with your authenticator app (Google Authenticator, Authy, etc.)</li>
                    <li>Enter the 6-digit code to confirm</li>
                    <li>Save your recovery codes in a safe place</li>
                </ol>
            </div>

            <p style="text-align:center;">
                <a href="{{ $loginUrl }}" class="btn">Go to Security Settings</a>
            </p>

            <p>If you need help, contact your team administrator or reply to this email.</p>
        </div>
        <div class="footer">
            <p>Okelcor Management Platform &mdash; Security Notice</p>
            <p>This message was sent because your account does not have 2FA enabled.</p>
        </div>
    </div>
</body>
</html>
