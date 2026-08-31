@extends('buyer.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your profile and settings')

@section('content')
<div class="dash-grid">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Profile Information</h2></div>
      <div class="card-pad">
        @if(session('success'))
        <div class="auth-success" style="margin-bottom:16px">{{ session('success') }}</div>
        @endif
        <form method="POST" action="#">
          @csrf
          <div class="form-grid-2">
            <div class="form-row">
              <label>First Name</label>
              <input type="text" name="first_name" value="{{ auth()->user()->given_names }}">
            </div>
            <div class="form-row">
              <label>Last Name</label>
              <input type="text" name="last_name" value="{{ auth()->user()->last_name }}">
            </div>
            <div class="form-row">
              <label>Email</label>
              <input type="email" value="{{ auth()->user()->email }}" disabled style="background:var(--paper)">
            </div>
            <div class="form-row">
              <label>Contact No.</label>
              <input type="text" name="contact_no" value="{{ auth()->user()->contact_no }}">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Change Password</h2></div>
      <div class="card-pad">
        <form method="POST" action="#">
          @csrf
          <div class="form-row">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="••••••••">
          </div>
          <div class="form-row">
            <label>New Password</label>
            <input type="password" name="password" placeholder="••••••••">
          </div>
          <div class="form-row">
            <label>Confirm New Password</label>
            <input type="password" name="password_confirmation" placeholder="••••••••">
          </div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h2>Account Details</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <div class="field-label">Account Type</div>
          <div class="field-value"><span class="stamp stamp-approved">Buyer</span></div>
        </div>
        <div>
          <div class="field-label">Status</div>
          <div class="field-value"><span class="stamp stamp-{{ auth()->user()->status }}">{{ ucfirst(auth()->user()->status) }}</span></div>
        </div>
        <div>
          <div class="field-label">Member Since</div>
          <div class="field-value mono">{{ auth()->user()->created_at->format('M d, Y') }}</div>
        </div>
        <div>
          <div class="field-label">Address</div>
          <div class="field-value" style="font-size:13px">
            {{ auth()->user()->barangay }}, {{ auth()->user()->municipality }}, {{ auth()->user()->province }}
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>Delivery Address</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:12px">
        <div class="address-card active-address">
          <div style="font-size:13px;font-weight:600">{{ auth()->user()->given_names }} {{ auth()->user()->last_name }}</div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:4px">
            {{ auth()->user()->house_no ? auth()->user()->house_no . ', ' : '' }}
            {{ auth()->user()->street ? auth()->user()->street . ', ' : '' }}
            {{ auth()->user()->barangay }}, {{ auth()->user()->municipality }}, {{ auth()->user()->province }}
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px">{{ auth()->user()->contact_no }}</div>
          <span class="stamp stamp-approved" style="margin-top:8px;display:inline-flex">Default</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h2>Payment Methods</h2><p>Verify a GCash or bank account once, then use it at checkout</p></div>
      </div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @forelse($paymentAccounts as $account)
        <div class="address-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
          <div>
            <div style="font-size:13px;font-weight:600">{{ $account->type === 'gcash' ? 'GCash' : ($account->type === 'paymaya' ? 'PayMaya' : ($account->bank_name ?: 'Bank Account')) }}</div>
            <div style="font-size:12.5px;color:var(--muted);margin-top:2px">{{ $account->account_name }} · {{ $account->account_number }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <span class="stamp stamp-approved">Verified</span>
            <form method="POST" action="{{ route('buyer.payment-accounts.destroy', $account->id) }}" onsubmit="return confirm('Remove this payment account?')">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline" title="Remove">
                @include('buyer.partials.icon', ['name' => 'trash', 'size' => 13])
              </button>
            </form>
          </div>
        </div>
        @empty
        <p style="font-size:13px;color:var(--muted)">No payment accounts connected yet.</p>
        @endforelse
        <div style="display:flex;gap:10px;margin-top:4px">
          <button type="button" class="btn btn-outline" onclick="openPaymentAccountModal('gcash')">Connect GCash</button>
          <button type="button" class="btn btn-outline" onclick="openPaymentAccountModal('paymaya')">Connect PayMaya</button>
          <button type="button" class="btn btn-outline" onclick="openPaymentAccountModal('bank')">Connect Bank Account</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="paymentAccountModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-head">
      <div><h3 id="paymentAccountTitle">Connect GCash</h3><p>We'll send a verification code to your registered email.</p></div>
      <button class="modal-close" type="button" data-modal-close>✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
      <input type="hidden" id="paymentAccountType" value="gcash">
      <div class="form-row" id="paymentBankNameRow" hidden>
        <label for="paymentBankName">Bank name</label>
        <input class="auth-input" id="paymentBankName" type="text" placeholder="e.g. BDO, BPI">
      </div>
      <div class="form-row">
        <label for="paymentAccountName">Account holder name</label>
        <input class="auth-input" id="paymentAccountName" type="text">
      </div>
      <div class="form-row">
        <label for="paymentAccountNumber" id="paymentAccountNumberLabel">GCash number</label>
        <input class="auth-input" id="paymentAccountNumber" type="text">
      </div>
      <div id="paymentAccountStep1">
        <button type="button" class="btn btn-primary btn-block" id="paymentSendCodeBtn" onclick="sendPaymentAccountCode()">Send verification code</button>
      </div>
      <div class="form-row" id="paymentAccountStep2" hidden>
        <label for="paymentAccountOtp">Verification code</label>
        <input class="auth-input" id="paymentAccountOtp" type="text" inputmode="numeric" maxlength="6" placeholder="6-digit code">
        <button type="button" class="btn btn-primary btn-block" style="margin-top:8px" onclick="verifyPaymentAccountCode()">Verify and Save</button>
      </div>
      <p id="paymentAccountMessage" style="font-size:12.5px;margin:0" hidden></p>
    </div>
  </div>
</div>
@endsection

@push('head')
<script>
const paymentTypeLabels = { gcash: 'GCash', paymaya: 'PayMaya', bank: 'Bank Account' };

function openPaymentAccountModal(type) {
  document.getElementById('paymentAccountType').value = type;
  document.getElementById('paymentAccountTitle').textContent = 'Connect ' + paymentTypeLabels[type];
  document.getElementById('paymentAccountNumberLabel').textContent = type === 'bank' ? 'Account number' : paymentTypeLabels[type] + ' number';
  document.getElementById('paymentBankNameRow').hidden = type !== 'bank';
  document.getElementById('paymentAccountStep1').hidden = false;
  document.getElementById('paymentAccountStep2').hidden = true;
  document.getElementById('paymentAccountName').value = '';
  document.getElementById('paymentAccountNumber').value = '';
  document.getElementById('paymentBankName').value = '';
  document.getElementById('paymentAccountOtp').value = '';
  const msg = document.getElementById('paymentAccountMessage');
  msg.hidden = true;
  document.getElementById('paymentAccountModal').classList.add('open');
}

function sendPaymentAccountCode() {
  const type = document.getElementById('paymentAccountType').value;
  const accountName = document.getElementById('paymentAccountName').value.trim();
  const accountNumber = document.getElementById('paymentAccountNumber').value.trim();
  const bankName = document.getElementById('paymentBankName').value.trim();
  const msg = document.getElementById('paymentAccountMessage');

  if (!accountName || !accountNumber || (type === 'bank' && !bankName)) {
    msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Please fill in all fields.';
    return;
  }

  const btn = document.getElementById('paymentSendCodeBtn');
  btn.disabled = true; btn.textContent = 'Sending…';

  fetch('{{ route('buyer.payment-accounts.send-code') }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
    body: JSON.stringify({ type, account_name: accountName, account_number: accountNumber, bank_name: bankName }),
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false; btn.textContent = 'Send verification code';
    msg.hidden = false;
    msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
    msg.textContent = data.message;
    if (data.success) {
      document.getElementById('paymentAccountStep1').hidden = true;
      document.getElementById('paymentAccountStep2').hidden = false;
    }
  })
  .catch(() => { btn.disabled = false; btn.textContent = 'Send verification code'; msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Network error. Try again.'; });
}

function verifyPaymentAccountCode() {
  const otp = document.getElementById('paymentAccountOtp').value.trim();
  const msg = document.getElementById('paymentAccountMessage');
  if (otp.length !== 6) {
    msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Enter the full 6-digit code.';
    return;
  }
  fetch('{{ route('buyer.payment-accounts.verify') }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
    body: JSON.stringify({ otp }),
  })
  .then(r => r.json())
  .then(data => {
    msg.hidden = false;
    msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
    msg.textContent = data.success ? 'Verified! Reloading…' : data.message;
    if (data.success) setTimeout(() => window.location.reload(), 600);
  })
  .catch(() => { msg.hidden = false; msg.style.color = 'var(--danger)'; msg.textContent = 'Network error. Try again.'; });
}
</script>
@endpush
