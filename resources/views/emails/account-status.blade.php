<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial, Helvetica, sans-serif;color:#1f2430">
  @php($supportEmail = \App\Models\Setting::current()->support_email)
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 0">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06)">
          <tr>
            <td style="background:#d9468f;padding:24px 32px">
              <span style="color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-.02em">PocketFinds</span>
            </td>
          </tr>
          <tr>
            <td style="padding:32px">
              <p style="margin:0 0 18px;font-size:15px;line-height:1.6">Dear {{ $name }},</p>

              @if($status === 'approved')
                <p style="margin:0 0 18px;font-size:15px;line-height:1.6">
                  We are pleased to inform you that your PocketFinds {{ $role }} account application has been
                  <strong style="color:#16a34a">approved</strong>. You may now sign in and begin using your account.
                </p>
                <p style="margin:0 0 24px;font-size:15px;line-height:1.6">Welcome aboard — we look forward to having you as part of the PocketFinds community.</p>
                <div style="text-align:center;margin:0 0 24px">
                  <a href="{{ url('/login') }}" style="display:inline-block;background:#d9468f;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px">Sign In to PocketFinds</a>
                </div>
              @elseif($status === 'activated')
                <p style="margin:0 0 18px;font-size:15px;line-height:1.6">
                  We are writing to let you know that your PocketFinds {{ $role }} account has been
                  <strong style="color:#16a34a">activated again</strong>. Your previous suspension has been lifted, and you may now sign in and resume using your account.
                </p>
                <p style="margin:0 0 24px;font-size:15px;line-height:1.6">Thank you for your patience while this was resolved.</p>
                <div style="text-align:center;margin:0 0 24px">
                  <a href="{{ url('/login') }}" style="display:inline-block;background:#d9468f;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px">Sign In to PocketFinds</a>
                </div>
              @elseif($status === 'rejected')
                <p style="margin:0 0 18px;font-size:15px;line-height:1.6">
                  Thank you for your interest in joining PocketFinds as a {{ $role }}. After careful review, we regret to
                  inform you that your account application has <strong style="color:#dc2626">not been approved</strong> at this time.
                </p>
                @if($reason)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin:0 0 20px">
                  <tr><td style="padding:14px 18px">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.04em">Reason</p>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#374151;white-space:pre-wrap">{{ $reason }}</p>
                  </td></tr>
                </table>
                @endif
                <p style="margin:0 0 18px;font-size:15px;line-height:1.6">
                  You are welcome to <strong>re-register using the same email address, name, and personal details</strong> —
                  your previous application no longer blocks a new one. Simply visit the registration page, correct
                  any issues noted above, and submit again.
                </p>
                <div style="text-align:center;margin:0 0 24px">
                  <a href="{{ url('/register/type') }}" style="display:inline-block;background:#d9468f;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px">Re-Register on PocketFinds</a>
                </div>
                <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#6b7280">
                  If you believe this decision was made in error or need help, please
                  <a href="mailto:{{ $supportEmail }}" style="color:#d9468f;font-weight:700">contact our support team</a>.
                </p>
              @elseif($status === 'suspended')
                <p style="margin:0 0 18px;font-size:15px;line-height:1.6">
                  We are writing to inform you that your PocketFinds {{ $role }} account has been
                  <strong style="color:#dc2626">suspended</strong>, effective immediately.
                </p>
                @if($reason)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin:0 0 20px">
                  <tr><td style="padding:14px 18px">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.04em">Reason</p>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#374151;white-space:pre-wrap">{{ $reason }}</p>
                  </td></tr>
                </table>
                @endif
                <p style="margin:0 0 18px;font-size:15px;line-height:1.6">
                  While your account is suspended, you will not be able to sign in or use PocketFinds services. If you
                  believe this action was taken in error, or would like to appeal this decision, please
                  <a href="mailto:{{ $supportEmail }}" style="color:#d9468f;font-weight:700">contact our support team</a>.
                </p>
              @endif

              <p style="margin:24px 0 0;font-size:15px;line-height:1.6">Sincerely,<br>The PocketFinds Team</p>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 32px;background:#f9fafb;border-top:1px solid #eef0f3">
              <p style="margin:0;font-size:11.5px;color:#9aa0ac;line-height:1.5">This is an automated message from PocketFinds. Please do not reply directly to this email — contact support through the app if you need help.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
