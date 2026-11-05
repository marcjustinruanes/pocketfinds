<section class="card account-following" id="following-shops" aria-labelledby="followingHeading">
 <div class="card-head"><div><h2 id="followingHeading">Following shops <span class="account-count">{{ $shops->count() }}</span></h2><p>The shops you’ve saved in one place.</p></div></div>
 <div class="card-pad">
  <div class="account-shop-grid">
   @forelse($shops as $shop)
    <article class="account-shop">
     <a class="account-shop-identity" href="{{ route('buyer.shop',$shop->username) }}">
      <img src="{{ $shop->shopPhotoUrl() }}" alt="" width="44" height="44" loading="lazy">
      <span><strong>{{ $shop->business_name ?: trim($shop->given_names.' '.$shop->last_name) }}</strong><small>{{ collect([$shop->municipality,$shop->province])->filter()->join(', ') }}</small></span>
     </a>
     <div class="account-shop-actions"><a href="{{ route('buyer.shop',$shop->username) }}">View shop</a><button type="button" data-account-unfollow="{{ route('buyer.shop.follow',$shop->username) }}">Unfollow</button></div>
    </article>
   @empty
    <p class="account-following-empty">You aren’t following any shops yet. <a href="{{ route('buyer.browse') }}">Explore products</a></p>
   @endforelse
  </div>
  <p id="followingFeedback" role="status" hidden></p>
 </div>
</section>
<script>
document.querySelectorAll('[data-account-unfollow]').forEach(button=>button.addEventListener('click',async()=>{
 const feedback=document.getElementById('followingFeedback');button.disabled=true;
 try{
  const response=await fetch(button.dataset.accountUnfollow,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}});
  const data=await response.json();if(!response.ok || data.following)throw new Error(data.message||'Unable to unfollow this shop. Try again.');
  location.hash='following-shops';location.reload();
 }catch(error){feedback.hidden=false;feedback.textContent=error.message||'Unable to unfollow. Try again.';button.disabled=false;}
}));
</script>
