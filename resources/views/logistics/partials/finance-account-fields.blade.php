<label for="{{ $prefix }}-channel">Payment channel</label><select id="{{ $prefix }}-channel" name="channel" required><option value="gcash">GCash</option></select>
<label for="{{ $prefix }}-institution">Provider</label><input id="{{ $prefix }}-institution" name="institution" value="GCash" readonly required>
<label for="{{ $prefix }}-name">GCash account name</label><input id="{{ $prefix }}-name" name="account_name" minlength="2" maxlength="150" required>
<label for="{{ $prefix }}-number">GCash mobile number</label><input id="{{ $prefix }}-number" name="account_number" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" placeholder="09XXXXXXXXX" required>
