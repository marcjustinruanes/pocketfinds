@extends('buyer.layout')
@section('title','Following shops')
@section('page-title','Following shops')
@section('content')
@include('commerce.messages')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
@forelse($shops as $shop)
 <article class="card"><div class="card-pad">
  <a href="{{ route('buyer.shop',$shop->username) }}" style="display:flex;align-items:center;gap:12px">
   <x-user-avatar :user="$shop" size="48" /> <strong>{{ $shop->business_name ?: trim($shop->given_names.' '.$shop->last_name) }}</strong>
  </a>
  <p>{{ $shop->municipality }}, {{ $shop->province }}</p>
  <button class="btn btn-outline" type="button" data-unfollow="{{ route('buyer.shop.follow',$shop->username) }}">Unfollow</button>
 </div></article>
@empty
 <p>You aren’t following any shops yet.</p>
@endforelse
</div>
<script>
document.querySelectorAll('[data-unfollow]').forEach(button=>button.addEventListener('click',async()=>{
 button.disabled=true;
 try{const response=await fetch(button.dataset.unfollow,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});if(!response.ok)throw new Error();location.reload();}
 catch(_){button.disabled=false;button.textContent='Try again';}
}));
</script>
@endsection
