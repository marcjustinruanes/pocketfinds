<section class="card account-following" id="following-shops" aria-labelledby="followingHeading">
 <div class="card-head"><div><h2 id="followingHeading">Following shops <span class="account-count">{{ $shops->count() }}</span></h2><p>The shops you’ve saved in one place.</p></div></div>
 <div class="card-pad">
  <div class="account-shop-grid">
   @forelse($shops as $shop)
    <article class="account-shop">
     <a class="account-shop-identity" href="{{ route('buyer.shop',$shop->username) }}">
      <img src="{{ $shop->shopPhotoUrl() }}" alt="" width="44" height="44" loading="lazy">
      <span><strong>{{ $shop->business_name ?: trim($shop->given_names.' '.$shop->last_name) }}</strong><small class="account-shop-category">{{ $shop->category?->name ?: $shop->category_other ?: 'General merchandise' }}</small><small>{{ collect([$shop->municipality,$shop->province])->filter()->join(', ') }}</small></span>
     </a>
     <div class="account-shop-actions"><a href="{{ route('buyer.shop',$shop->username) }}" aria-label="View shop" title="View shop">@include('buyer.partials.icon',['name'=>'shop','size'=>18])</a><button type="button" data-account-unfollow="{{ route('buyer.shop.follow',$shop->username) }}" aria-label="Unfollow" title="Unfollow shop"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5a5.5 5.5 0 0 0-8 1 5.5 5.5 0 0 0-9 6l9 9 5-5M16 8h6"/></svg></button></div>
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
