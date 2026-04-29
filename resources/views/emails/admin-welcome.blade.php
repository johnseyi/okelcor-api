<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Okelcor Admin Credentials</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a1a; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px 30px; }
        .body p { color: #444444; line-height: 1.6; margin: 0 0 16px; }
        .credentials { background: #f0f7ff; border: 2px solid #3b82f6; border-radius: 6px; padding: 20px; margin: 24px 0; }
        .credentials h3 { margin: 0 0 12px; color: #1e40af; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
        .credentials p { margin: 8px 0; font-size: 15px; color: #1a1a1a; }
        .credentials strong { display: inline-block; width: 120px; color: #555; font-size: 13px; }
        .credentials code { background: #1a1a1a; color: #f0f0f0; padding: 4px 10px; border-radius: 4px; font-family: monospace; font-size: 15px; letter-spacing: 0.05em; }
        .btn { display: inline-block; margin: 24px 0; padding: 14px 32px; background: #e63946; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; }
        .warning { background: #fff8e1; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 0 4px 4px 0; margin: 16px 0; }
        .warning p { color: #92400e; margin: 0; font-size: 14px; }
        .footer { padding: 20px 30px; background: #f9f9f9; text-align: center; }
        .footer p { color: #999999; font-size: 12px; margin: 4px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Okelcor Admin</h1>
        </div>
        <div class="body">
            <p>Hello {{ $admin->first_name ?? $admin->name }},</p>
            <p>Your admin account on the Okelcor management platform has been created. Your login credentials are below — use them to sign in now.</p>

            <div class="credentials">
                <h3>Your Login Credentials</h3>
                <p><strong>Login URL</strong> <a href="{{ $loginUrl }}" style="color:#e63946;">{{ $loginUrl }}</a></p>
                <p><strong>Email</strong> {{ $admin->email }}</p>
                <p><strong>Password</strong> <code>{{ $temporaryPassword }}</code></p>
                <p><strong>Role</strong> {{ ucfirst(str_replace('_', ' ', $admin->role)) }}</p>
            </div>

            <div class="warning">
                <p><strong>Important:</strong> This is a one-time temporary password. You will be required to set a new password immediately after your first login.</p>
            </div>

            <p style="text-align:center;">
                <a href="{{ $loginUrl }}" class="btn">Log In to Admin Panel</a>
            </p>

            <p>If you were not expecting this invitation, please contact <a href="mailto:support@okelcor.com">support@okelcor.com</a> immediately.</p>
        </div>
        <div class="footer">
            <p>Okelcor Management Platform</p>
            <p><a href="{{ $loginUrl }}" style="color:#e63946;">{{ $loginUrl }}</a></p>
        </div>
    </div>
</body>
</html>
