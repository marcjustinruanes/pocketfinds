@extends('seller.layout')
@section('title', 'Vouchers')
@section('page-title', 'Vouchers')
@section('page-sub', 'Create discount codes buyers can apply to your shop at checkout')

@section('content')

@if(session('voucher_success'))
<div class="modal-overlay open" id="voucherResultModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-body" style="text-align:center;padding:30px 26px 20px">
      <span style="width:48px;height:48px;border-radius:50%;background:var(--success-soft);color:var(--success);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
        @include('seller.partials.icon',['name'=>'check-circle','size'=>24])
      </span>
      <h3 style="font-family:var(--font-display);font-size:16px;margin:0 0 6px">Success</h3>
      <p style="font-size:13px;color:var(--muted);margin:0">{{ session('voucher_success') }}</p>
    </div>
    <div class="modal-foot" style="justify-content:center">
      <button class="btn btn-primary" type="button" data-modal-close>OK</button>
    </div>
  </div>
</div>
@endif

<div class="filter-bar">
  <div></div>
  <button class="btn btn-primary" data-modal="addVoucherModal">
    @include('seller.partials.icon',['name'=>'plus','size'=>14]) Create Voucher
  </button>
</div>

<div class="card">
  <div class="card-pad" style="padding:0">
    <table class="tbl" style="table-layout:fixed">
      <thead>
        <tr style="text-align:center">
          <th style="text-align:center;width:16%">Code</th>
          <th style="text-align:center;width:16%">Discount</th>
          <th style="text-align:center;width:16%">Min. Spend</th>
          <th style="text-align:center;width:14%">Usage</th>
          <th style="text-align:center;width:16%">Expires</th>
          <th style="text-align:center;width:10%">Status</th>
          <th style="text-align:center;width:12%">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($vouchers as $voucher)
        <tr style="white-space:nowrap">
          <td style="text-align:center;font-family:var(--font-mono);font-weight:700">{{ $voucher->code }}</td>
          <td style="text-align:center">
            @if($voucher->type === 'free_shipping')
              <span class="stamp stamp-approved">Free Shipping</span>
            @else
              ₱{{ number_format($voucher->discount_amount, 2) }}
            @endif
          </td>
          <td style="text-align:center">₱{{ number_format($voucher->minimum_spend, 2) }}</td>
          <td style="text-align:center">{{ $voucher->used_count }}{{ $voucher->usage_limit ? ' / ' . $voucher->usage_limit : '' }}</td>
          <td style="text-align:center">{{ $voucher->expires_at?->format('M d, Y') ?? 'No expiry' }}</td>
          <td style="text-align:center">
            @if(!$voucher->is_active)
              <span class="stamp stamp-rejected">Inactive</span>
            @elseif($voucher->expires_at && $voucher->expires_at->isPast())
              <span class="stamp stamp-rejected">Expired</span>
            @elseif($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit)
              <span class="stamp stamp-rejected">Used up</span>
            @else
              <span class="stamp stamp-approved">Active</span>
            @endif
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:6px;justify-content:center">
              <form method="POST" action="{{ route('seller.vouchers.update', $voucher) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="{{ $voucher->is_active ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm btn-outline" title="{{ $voucher->is_active ? 'Deactivate' : 'Activate' }}">
                  @include('seller.partials.icon',['name'=>$voucher->is_active ? 'x' : 'check','size'=>13])
                </button>
              </form>
              <form method="POST" action="{{ route('seller.vouchers.destroy', $voucher) }}" onsubmit="return confirm('Delete this voucher?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline" title="Delete">
                  @include('seller.partials.icon',['name'=>'trash','size'=>13])
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7">
          <div class="empty">
            @include('seller.partials.icon',['name'=>'tag','size'=>28,'class'=>'ic'])
            <h3>No vouchers yet</h3>
            <p>Create a voucher to offer buyers a discount on your shop.</p>
          </div>
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay {{ $errors->has('code') ? 'open' : '' }}" id="addVoucherModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head">
      <div><h3>Create Voucher</h3><p>Buyers will see this at checkout once it applies to their cart.</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <form method="POST" action="{{ route('seller.vouchers.store') }}">
      @csrf
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-row">
          <label for="voucherCode">Voucher code</label>
          <input class="auth-input" id="voucherCode" name="code" type="text" maxlength="30" placeholder="e.g. SHOP50" required style="text-transform:uppercase">
          @error('code')<span style="color:var(--danger);font-size:12px">{{ $message }}</span>@enderror
        </div>
        <div class="form-row">
          <label>Voucher type</label>
          <div style="display:flex;gap:16px;font-size:13px">
            <label style="display:flex;align-items:center;gap:6px;font-weight:400">
              <input type="radio" name="type" value="amount" {{ old('type', 'amount') === 'amount' ? 'checked' : '' }} onchange="toggleVoucherType()"> Discount amount
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:400">
              <input type="radio" name="type" value="free_shipping" {{ old('type') === 'free_shipping' ? 'checked' : '' }} onchange="toggleVoucherType()"> Free shipping
            </label>
          </div>
        </div>
        <div class="form-row" id="voucherDiscountRow">
          <label for="voucherDiscount">Discount amount (₱)</label>
          <input class="auth-input" id="voucherDiscount" name="discount_amount" type="number" min="1" step="0.01">
        </div>
        <div class="form-row">
          <label for="voucherMinimum">Minimum spend (₱)</label>
          <input class="auth-input" id="voucherMinimum" name="minimum_spend" type="number" min="0" step="0.01" value="0">
        </div>
        <div class="form-row">
          <label for="voucherLimit">Usage limit (optional)</label>
          <input class="auth-input" id="voucherLimit" name="usage_limit" type="number" min="1" placeholder="Leave blank for unlimited">
        </div>
        <div class="form-row">
          <label for="voucherExpiry">Expiry date (optional)</label>
          <input class="auth-input" id="voucherExpiry" name="expires_at" type="date">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Create Voucher</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('head')
<script>
function toggleVoucherType() {
  const isFreeShipping = document.querySelector('input[name="type"]:checked').value === 'free_shipping';
  const row = document.getElementById('voucherDiscountRow');
  const input = document.getElementById('voucherDiscount');
  row.style.display = isFreeShipping ? 'none' : '';
  input.required = !isFreeShipping;
  if (isFreeShipping) input.value = '';
}
document.addEventListener('DOMContentLoaded', toggleVoucherType);
</script>
@endpush
