@php $productError = $errors->first('images') ?: $errors->first('video'); @endphp
@if(session('product_success') || $productError)
<div class="modal-overlay open" id="productResultModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-body" style="text-align:center;padding:30px 26px 20px">
      @if(session('product_success'))
        <span style="width:48px;height:48px;border-radius:50%;background:var(--success-soft);color:var(--success);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
          @include('seller.partials.icon',['name'=>'check-circle','size'=>24])
        </span>
        <h3 style="font-family:var(--font-display);font-size:16px;margin:0 0 6px">Success</h3>
        <p style="font-size:13px;color:var(--muted);margin:0">{{ session('product_success') }}</p>
      @else
        <span style="width:48px;height:48px;border-radius:50%;background:var(--danger-soft);color:var(--danger);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
          @include('seller.partials.icon',['name'=>'x','size'=>24])
        </span>
        <h3 style="font-family:var(--font-display);font-size:16px;margin:0 0 6px">Something went wrong</h3>
        <p style="font-size:13px;color:var(--muted);margin:0">{{ $productError }}</p>
      @endif
    </div>
    <div class="modal-foot" style="justify-content:center">
      <button class="btn btn-primary" type="button" data-modal-close>OK</button>
    </div>
  </div>
</div>
@endif
