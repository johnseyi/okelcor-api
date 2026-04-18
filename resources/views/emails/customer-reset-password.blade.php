<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a1a; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px 30px; }
        .body p { color: #444444; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; margin: 24px 0; padding: 14px 32px; background: #e63946; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; }
        .footer { padding: 20px 30px; background: #f9f9f9; text-align: center; }
        .footer p { color: #999999; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Okelcor</h1>
        </div>
        <div class="body">
            <p>Hello {{ $customer->first_name }},</p>
            <p>We received a request to reset your Okelcor password. Click the button below to choose a new password.</p>
            <p style="text-align:center;">
                <a href="{{ $resetUrl }}" class="btn">Reset Password</a>
            </p>
            <p>This link will expire in 60 minutes.</p>
            <p>If you did not request a password reset, you can ignore this email — your password will not be changed.</p>
        </div>
        <div class="footer">
            <p>If you're having trouble clicking the button, copy and paste the URL below into your browser:<br>
            <a href="{{ $resetUrl }}" style="color:#e63946;word-break:break-all;">{{ $resetUrl }}</a></p>
        </div>
    </div>
</body>
</html>
