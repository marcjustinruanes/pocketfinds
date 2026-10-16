{{ $rider->business_name }} has invited you to PocketFinds as a rider.

Set your password using this link:
{{ route('rider.activation', ['user' => $rider->id, 'token' => $token]) }}

The link expires after 24 hours. It can be used only once. If it expires, ask your logistics company to send another invitation.
