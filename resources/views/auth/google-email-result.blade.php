<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email verification ? PocketFinds</title>@include('partials.favicon')</head>
<body><p>{{ $error ?: 'Email verified. You can return to your registration.' }}</p>
<script>
if (window.opener) {
 window.opener.postMessage({type:'pocketfinds-email-verification', proof:@json($proof), error:@json($error)}, '*');
 window.close();
}
</script></body></html>
