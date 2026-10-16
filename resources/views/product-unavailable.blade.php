<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Product unavailable · PocketFinds</title></head>
<body style="font-family:system-ui,sans-serif;background:#faf7f9;color:#302631;margin:0;padding:40px">
<main style="max-width:600px;margin:10vh auto;background:white;padding:32px;border-radius:16px">
<a href="{{ auth()->check() && auth()->user()->account_type === 'buyer' ? route('buyer.browse') : url('/') }}">PocketFinds</a>
<h1>Product unavailable</h1><p>This product is no longer available. You can continue browsing other products.</p>
<a href="{{ auth()->check() && auth()->user()->account_type === 'buyer' ? route('buyer.browse') : url('/') }}">Continue shopping</a>
</main></body></html>
