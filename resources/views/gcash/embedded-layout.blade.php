<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}">
@stack('head')
<link rel="stylesheet" href="{{ asset('css/order-details.css') }}?v={{ filemtime(public_path('css/order-details.css')) }}">
<link rel="stylesheet" href="{{ asset('css/gcash-order-modal.css') }}?v={{ filemtime(public_path('css/gcash-order-modal.css')) }}">
</head><body class="gcash-embedded">
<main data-order-summary>@yield('content')</main>
@stack('scripts')
<script>
const paymentPage=document.querySelector('[data-purchase]');
if(paymentPage)parent.postMessage({type:'gcash-details-state',id:paymentPage.dataset.purchase,state:paymentPage.dataset.paymentState},location.origin);
document.addEventListener('click', event => {
 const link=event.target.closest('a[href]');
 if(!link||link.target==='_blank'||link.hasAttribute('data-private-receipt'))return;
 const url=new URL(link.href,location.href);
 if(url.origin!==location.origin)return;
 if(url.pathname===location.pathname){url.searchParams.set('embedded','1');link.href=url.href;}
 else link.target='_top';
});
document.addEventListener('keydown',event=>{
 if(event.key!=='Escape'||document.querySelector('dialog[open]'))return;
 try{if(parent.document.querySelector('#gcash-confirm[open],#gcash-receipt[open],.order-payment-preview-dialog[open]'))return;}catch{}
 parent.postMessage({type:'gcash-details-close'},location.origin);
});
</script>
</body></html>
