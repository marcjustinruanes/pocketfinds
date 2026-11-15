<div class="platform-review-summary">
  <x-user-avatar :user="$user" size="46" />
  <div class="platform-review-person"><strong>{{ collect([$user->given_names,$user->middle_name,$user->last_name,$user->suffix instanceof \BackedEnum ? $user->suffix->value : $user->suffix])->filter()->implode(' ') }}</strong><span>{{ $user->email }}</span></div>
  <div class="platform-review-state"><span>{{ $user->isHubStaff() ? 'Hub staff' : ($user->is_admin ? 'Platform admin' : ucfirst($user->account_type)) }}</span><span class="stamp stamp-{{ $user->status }}">{{ ucfirst($user->status) }}</span></div>
</div>
