<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Messages — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}?v={{ filemtime(public_path('css/buyer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-pages.css') }}?v={{ filemtime(public_path('css/buyer-pages.css')) }}">
@include('account.partials.messages-embedded-styles')
<script>
document.addEventListener('keydown',event=>{
 if(event.key==='Escape'&&!document.querySelector('.modal-overlay.open,.media-viewer.open')){
  window.parent.document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));
 }
});
document.addEventListener('click',event=>{
 const link=event.target.closest('a');if(!link)return;
 const url=new URL(link.href,location.href);
 if(url.origin===location.origin&&url.pathname==={{ Illuminate\Support\Js::from(route('buyer.messages',[],false)) }}){
  url.searchParams.set('embedded','1');link.href=url.href;
 }else{link.target='_top';}
});
</script>
</head>
<body class="buyer buyer-pages chat-embedded">
@include('buyer.partials.messages-panel')
</body>
</html>
