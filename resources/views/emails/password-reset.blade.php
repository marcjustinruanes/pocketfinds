<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset your PocketFinds password</title></head>
<body style="margin:0;padding:32px 16px;background:#faf5f8;font-family:Arial,sans-serif;color:#27272a">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px">
        <tr><td align="center" style="padding:0 0 24px">
            <span style="font-family:Arial,sans-serif;font-size:26px;font-weight:800;letter-spacing:-1px;color:#593346">Pocket<span style="color:#bc387f">Finds</span></span>
        </td></tr>
        <tr><td style="padding:32px;background:#ffffff;border:1px solid #eee2e9;border-radius:14px">
            <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3">Reset your password</h1>
            <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#71717a">We received a request to reset your PocketFinds password. Use the button below to choose a new one.</p>
            <table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#bc387f" style="border-radius:8px">
                <a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#bc387f;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none">Reset password</a>
            </td></tr></table>
            <p style="margin:24px 0 12px;font-size:13px;line-height:1.7;color:#71717a">This link expires in {{ $expiresIn }} minutes and can only be used once.</p>
            <p style="margin:0;font-size:13px;line-height:1.7;color:#71717a">If you didn’t request a password reset, you can ignore this email.</p>
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
