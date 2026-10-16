<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'PocketFinds')</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/register.css') }}">
  <style>
    body { margin:0; background:#faf7fa; color:#302733; font-family:Arial,sans-serif; }
    .auth-card { display:block; box-sizing:border-box; width:calc(100% - 32px); background:white; border:1px solid #eadfe8; border-radius:18px; box-shadow:0 12px 40px #3027330a; }
    .auth-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .auth-field.full,.full { grid-column:1 / -1; }
    .auth-label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; }
    .auth-input { box-sizing:border-box; width:100%; min-height:44px; border:1px solid #d9cfd9; border-radius:8px; padding:10px; background:white; color:inherit; font:inherit; }
    .auth-btn { width:100%; margin-top:18px; min-height:44px; border:0; border-radius:8px; background:#c73882; color:white; font:inherit; font-weight:600; cursor:pointer; }
    .auth-btn:disabled { opacity:.6; cursor:wait; }
    .field-error,.auth-error { color:#ad2437; font-size:13px; }
    .field-hint { color:#6c5f70; font-size:12px; }
    a { color:#a62369; }
    [hidden] { display:none !important; }
    :focus-visible { outline:3px solid #2d7598; outline-offset:2px; }
    @media(max-width:560px) { .auth-form-grid { grid-template-columns:minmax(0,1fr); } }
  </style>
</head>
<body>@yield('content')</body>
</html>
