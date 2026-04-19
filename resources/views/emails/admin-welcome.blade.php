<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Okelcor Admin Access</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #1a1a1a; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px 30px; }
        .body p { color: #444444; line-height: 1.6; margin: 0 0 16px; }
        .credentials { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin: 24px 0; }
        .credentials p { margin: 6px 0; font-size: 15px; }
        .credentials strong { color: #1a1a1a; }
        .credentials code { background: #eaeaea; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 14px; }
        .btn { display: inline-block; margin: 24px 0; padding: 14px 32px; background: #e63946; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; }
        .warning { background: #fff8e1; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 0 4px 4px 0; margin: 16px 0; }
        .warning p { color: #92400e; margin: 0; font-size: 14px; }
        .footer { padding: 20px 30px; background: #f9f9f9; text-align: center; }
        .footer p { color: #999999; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Okelcor Admin</h1>
        </div>
        <div class="body">
            <p>Hello {{ $admin->first_name ?? $admin->name }},</p>
            <p>You have been given access to the Okelcor admin panel. Please log in and change your password immediately.</p>

            <div class="credentials">
                <p><strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $admin->role)) }}</p>
                <p><strong>Email:</strong> {{ $admin->email }}</p>
                <p><strong>Temporary password:</strong> <code>{{ $temporaryPassword }}</code></p>
            </div>

            <div class="warning">
                <p><strong>Important:</strong> This is a temporary password. You will be required to change it on your first login.</p>
            </div>

            <p style="text-align:center;">
                <a href="{{ $loginUrl }}" class="btn">Log In to Admin Panel</a>
            </p>

            <p>If you were not expecting this invitation, please contact your system administrator immediately.</p>
        </div>
        <div class="footer">
            <p>Login URL: <a href="{{ $loginUrl }}" style="color:#e63946;">{{ $loginUrl }}</a></p>
        </div>
    </div>
</body>
</html>
