@props(['id','title'])
<div class="finance-modal" id="{{ $id }}" data-finance-modal hidden>
 <section class="finance-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" tabindex="-1">
  <div class="finance-dialog-head"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" data-finance-close aria-label="Close">@include('seller.partials.icon',['name'=>'x','size'=>19])</button></div>
  <div class="finance-dialog-body">{{ $slot }}</div>
 </section>
</div>
