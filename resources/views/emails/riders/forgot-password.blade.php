<!DOCTYPE html>
<html>
<head>
    <title>Reset Your Password - Sway Rider</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #0DAA75;">Reset Your Password</h2>
        <p>Hello {{ $name }},</p>
        <p>You recently requested to reset your password for your Sway Rider account. Use the code below to proceed:</p>
        
        <div style="background-color: #f4f4f4; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;">
            <h1 style="margin: 0; color: #333; letter-spacing: 5px;">{{ $code }}</h1>
        </div>

        <p>This code will expire in 15 minutes.</p>
        <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
        
        <p>Best regards,<br>The Sway Rider Team</p>
    </div>
</body>
</html>
