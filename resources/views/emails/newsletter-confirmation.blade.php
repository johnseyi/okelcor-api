<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm your newsletter subscription</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <p>Hello,</p>
    <p>Please confirm your newsletter subscription by clicking the link below:</p>
    <p>
        <a href="{{ $confirmUrl }}">{{ $confirmUrl }}</a>
    </p>
    <p>If you did not request this, you can ignore this email.</p>
</body>
</html>
