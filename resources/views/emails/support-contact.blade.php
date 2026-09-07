<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Request</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial, Helvetica, sans-serif;color:#1f2430">
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
              <p style="margin:0 0 18px;font-size:15px;line-height:1.6">A visitor submitted a support request through the Contact Support form.</p>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #eef0f3;border-radius:10px;margin:0 0 20px">
                <tr><td style="padding:14px 18px">
                  <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">From</p>
                  <p style="margin:0 0 12px;font-size:14px;color:#374151">{{ $email }}</p>
                  @if(!empty($context))
                  <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Context</p>
                  <p style="margin:0 0 12px;font-size:14px;color:#374151">{{ $context }}</p>
                  @endif
                  <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">Message</p>
                  <p style="margin:0;font-size:14px;line-height:1.6;color:#374151;white-space:pre-wrap">{{ $body }}</p>
                </td></tr>
              </table>

              <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6">Reply directly to this email to respond — it's addressed back to {{ $email }}.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
