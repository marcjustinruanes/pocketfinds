<label class="auth-label" for="middle_name">Middle name <span class="auth-required">*</span></label>
<input class="auth-input" id="middle_name" name="middle_name" type="text" placeholder="Santos" minlength="2" maxlength="50" value="{{ old('middle_name') }}" @required(!old('no_middle_name')) @disabled(old('no_middle_name'))>
<label class="registration-no-middle-name"><input type="checkbox" id="no_middle_name" name="no_middle_name" value="1" @checked(old('no_middle_name'))> <span>I have no middle name</span></label>
