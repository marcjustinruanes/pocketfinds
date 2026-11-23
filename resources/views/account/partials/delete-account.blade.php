<style>
.account-danger-zone .card-head{display:flex;align-items:center;justify-content:space-between}
.account-danger-zone-body:empty{display:none}
.account-danger-zone{border-color:#f0d8df!important}
.account-danger-zone .card-head h2{color:#9f334f}
.account-danger-zone-body{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.account-danger-zone-body>p{margin:0;max-width:460px;color:#7e6470;font-size:12px;line-height:1.65;flex:1 1 330px}
.account-danger-error{flex-basis:100%;margin:0;color:#b1243c;font-size:12px}
.account-delete-modal{max-width:430px}
.account-delete-modal .modal-body{display:flex;flex-direction:column;gap:14px}
</style>
    <div class="card account-danger-zone" data-account-panel="account-security">
      <div class="card-head"><div><h2>Delete My Account</h2><p>Remove your profile, saved addresses, cart, and follows.</p></div><button type="button" class="btn btn-danger" data-modal-open="deleteAccountModal">Delete account</button></div>
      <div class="card-pad account-danger-zone-body">
        @error('delete_account')<p class="account-danger-error" role="alert">{{ $message }}</p>@enderror
        @error('confirmation')<p class="account-danger-error" role="alert">{{ $message }}</p>@enderror
        @error('password')<p class="account-danger-error" role="alert">{{ $message }}</p>@enderror
      </div>
    </div>
<div class="modal-overlay" id="deleteAccountModal" aria-hidden="true">
  <div class="modal pf-confirm account-delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteAccountTitle">
    <div class="modal-head">
      <div><h3 id="deleteAccountTitle">Delete your account?</h3><p>This permanently removes your profile and signs you out.</p></div>
    </div>
    <form method="POST" action="{{ route('account.destroy') }}">
      @csrf @method('DELETE')
      <div class="modal-body"><p>Completed order records remain for fulfilment, refunds, and accounting. This action signs you out and cannot be undone.</p>
        <div class="form-row"><label for="deleteAccountPassword">Current password</label><input id="deleteAccountPassword" type="password" name="password" autocomplete="current-password" required></div>
        <div class="form-row"><label for="deleteAccountConfirmation">Type “yes i confirm” to continue</label><input id="deleteAccountConfirmation" type="text" name="confirmation" autocomplete="off" required></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Delete account</button></div>
    </form>
  </div>
</div>
