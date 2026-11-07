@php
 $u=auth()->user();
 $role=$u->is_admin?'admin':'seller';
 $vouchers=\Illuminate\Support\Facades\DB::table('commerce_vouchers')->when($u->is_admin,fn($q)=>$q->whereNull('seller_id'),fn($q)=>$q->where('seller_id',$u->id))->get();
@endphp
<section class="card platform-vouchers-panel" id="platform-vouchers" aria-labelledby="platform-vouchers-title">
  <div class="card-head"><div><h2 id="platform-vouchers-title">{{ $u->is_admin?'Platform and shipping':'Store' }} vouchers</h2><p>Create and manage discounts for checkout.</p></div></div>
  <div class="card-pad">
    <form class="platform-voucher-form" method="POST" action="{{ route($role.'.commerce.voucher') }}">
      @csrf
      <div class="platform-voucher-fields">
        <label class="form-row">Code (4–12 letters/digits)<input name="code" pattern="[A-Za-z0-9]{4,12}" minlength="4" maxlength="12" required></label>
        <label class="form-row">Type<select class="select" name="kind">@if($u->is_admin)<option value="platform">Platform discount</option><option value="shipping">Shipping discount</option>@else<option value="store">Store discount</option>@endif</select></label>
        <label class="form-row">Discount<select class="select" name="discount_type"><option value="fixed">Fixed PHP amount</option><option value="percent">Percentage</option></select></label>
        <label class="form-row">Amount / percent<input name="amount" type="number" min="0.01" step="0.01" required></label>
        <label class="form-row">Maximum discount (optional PHP)<input name="cap" type="number" min="0.01" step="0.01"></label>
        <label class="form-row">Minimum spend (PHP)<input name="minimum_spend" type="number" min="0" step="0.01" value="0" required></label>
        <label class="form-row">Starts<input name="starts_at" type="datetime-local" required></label>
        <label class="form-row">Ends<input name="ends_at" type="datetime-local" required></label>
        <label class="form-row">Total uses<input name="usage_limit" type="number" min="1" required></label>
        <label class="form-row">Uses per buyer<input name="per_buyer_limit" type="number" min="1" value="1" required></label>
      </div>
      <button class="btn btn-primary" type="submit">Create voucher</button>
    </form>

    <div class="platform-voucher-list">
      @forelse($vouchers as $voucher)
      <article class="platform-voucher-row">
        <div class="platform-voucher-summary">
          <div class="platform-voucher-code">{{ $voucher->code }}</div>
          <div class="platform-voucher-meta"><span>{{ ucfirst($voucher->kind) }}</span><span>{{ $voucher->starts_at }} – {{ $voucher->ends_at }}</span></div>
        </div>
        <span class="platform-voucher-status {{ $voucher->is_active ? 'is-active' : 'is-ended' }}">{{ $voucher->is_active ? 'Active' : 'Ended' }}</span>
        @if($voucher->is_active)
        <form class="platform-voucher-end" method="POST" action="{{ route($role.'.commerce.end',['voucher',$voucher->id]) }}">
          @csrf
          <button class="btn btn-outline" type="submit">End voucher</button>
        </form>
        @endif
      </article>
      @empty
      <div class="platform-voucher-empty"><strong>No vouchers created yet</strong><span>Created vouchers will appear here.</span></div>
      @endforelse
    </div>
  </div>
</section>
