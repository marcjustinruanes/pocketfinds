<div class="card" id="change-email">
  <div class="card-head"><div><h2>Change Email</h2><p>Verify your new Gmail address before changing your account email.</p></div></div>
  <div class="card-pad">
    <form method="POST" action="{{ route('account.email.code') }}">@csrf
      <div class="form-row"><label for="newBuyerEmail">New Gmail address</label><input id="newBuyerEmail" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
      <div class="form-row"><label for="emailCurrentPassword">Current password</label><input id="emailCurrentPassword" name="current_password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary">Send Verification Code</button>
    </form>
    <form method="POST" action="{{ route('account.email.verify') }}" style="margin-top:20px">@csrf
      <div class="form-row"><label for="buyerEmailOtp">Verification code</label><input type="text" id="buyerEmailOtp" name="otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autocomplete="one-time-code"><small>Enter the code sent to your new email. It expires in five minutes.</small></div>
      <button class="btn btn-primary">Verify and Update Email</button>
    </form>
  </div>
</div>
