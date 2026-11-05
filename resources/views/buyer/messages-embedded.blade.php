<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Messages — PocketFinds</title>
<link rel="stylesheet" href="{{ asset('css/buyer.css') }}?v={{ filemtime(public_path('css/buyer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/buyer-pages.css') }}?v={{ filemtime(public_path('css/buyer-pages.css')) }}">
<style>
html,body{margin:0;padding:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;flex-direction:column}
body>.gcash-context{flex:none;margin:0;border-radius:0;max-height:120px;overflow:auto}
body.buyer-pages>.chat-shell{flex:1 1 0;min-height:0!important;max-height:none!important;width:100%;height:100%!important;margin:0;border:0;border-radius:0;grid-template-columns:210px minmax(0,1fr)!important}
body.buyer-pages>.chat-shell>.chat-main{min-width:0;width:100%}
body.chat-embedded>.chat-shell>.chat-list{display:block!important;min-width:0}
body.chat-embedded>.chat-shell>.chat-main{display:flex!important}
body.chat-embedded>.chat-shell .chat-mobile-back{display:none}
@media(max-width:520px){
 body.chat-embedded>.chat-shell{grid-template-columns:minmax(0,1fr)!important}
 body.chat-embedded>.chat-shell[data-has-conversation="true"]>.chat-list{display:none!important}
 body.chat-embedded>.chat-shell[data-has-conversation="false"]>.chat-main{display:none!important}
 body.chat-embedded>.chat-shell .chat-mobile-back{display:inline-flex}
}
</style>
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
