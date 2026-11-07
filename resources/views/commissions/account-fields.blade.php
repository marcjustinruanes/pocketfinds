<label>GCash account name<input name="account_name" maxlength="150" value="{{ $account?->account_name }}" required autocomplete="off"></label>
<label>GCash mobile number<input name="account_number" type="text" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" value="{{ $account?->account_number }}" required autocomplete="off"></label>
