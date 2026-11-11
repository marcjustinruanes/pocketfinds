<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>Messages — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}?v={{ filemtime(public_path('css/buyer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-pages.css') }}?v={{ filemtime(public_path('css/buyer-pages.css')) }}">
@include('account.partials.messages-embedded-styles')
@stack('head')</head><body class="buyer buyer-pages chat-embedded">@yield('content')@stack('scripts')
<script>
document.addEventListener('click',event=>{const link=event.target.closest('a[href]');if(!link||link.target==='_blank'||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button!==0)return;const target=new URL(link.href,location.origin);if(target.origin===location.origin&&target.pathname==='/seller/messages'){event.preventDefault();target.searchParams.set('embedded','1');location.assign(target.href);}else if(target.origin===location.origin){link.target='_top';}});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!document.querySelector('.modal-overlay.open,.media-viewer.open'))parent.postMessage('seller-close-messages',location.origin);});
</script></body></html>
