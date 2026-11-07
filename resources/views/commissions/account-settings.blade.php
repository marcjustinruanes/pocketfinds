<h3>Admin Commission Settings</h3>
<p>Activating a receiving account does not enable commission enrollment. Edits create a new revision; submitted receipts keep their original destination.</p>
@if(!$accountSettings)
<p role="alert">Account management installation is required. New commission payments are unavailable.</p>
@else
@if($activeAccount)
<div class="commission-policy"><div><strong>{{ $activeAccount->account_name }}</strong><p>GCash / {{ $activeAccount->account_number }}</p></div><span class="commission-badge is-success">Active receiving account</span></div>
@else<p role="status">No active GCash receiving account. Sellers cannot submit new commission payments.</p>@endif
<details class="commission-action"><summary>Add GCash receiving account</summary>
<form method="POST" action="{{ route('commissions.accounts') }}" data-confirm="Save this receiving account as inactive?">@csrf
<input type="hidden" name="action" value="create"><input type="hidden" name="revision" value="{{ $accountSettings->revision }}">
@include('commissions.account-fields',['account'=>null])
<label>Reason<textarea name="reason" minlength="3" maxlength="1000" required></textarea></label>
<button class="btn" type="submit">Add inactive account</button></form></details>
@foreach($accounts as $account)
<article class="commission-record"><h3>{{ $account->account_name }}</h3><p>GCash / {{ $account->account_number }} / {{ $account->created_at }} <span class="commission-badge">{{ $activeAccount?->id === $account->id ? 'Active' : 'Inactive revision' }}</span></p>
<details><summary>Edit account details</summary><p>Editing the active account replaces its instructions for new payments only.</p>
<form method="POST" action="{{ route('commissions.accounts') }}" data-confirm="Save a new receiving-account revision?">@csrf
<input type="hidden" name="action" value="edit"><input type="hidden" name="account_id" value="{{ $account->id }}"><input type="hidden" name="revision" value="{{ $accountSettings->revision }}">
@include('commissions.account-fields')
<label>Reason<textarea name="reason" minlength="3" maxlength="1000" required></textarea></label><button class="btn">Save revision</button></form></details>
<details><summary>{{ $activeAccount?->id === $account->id ? 'Deactivate' : 'Activate' }} receiving account</summary>
<form method="POST" action="{{ route('commissions.accounts') }}" data-confirm="Change the active GCash receiving account?">@csrf
<input type="hidden" name="action" value="{{ $activeAccount?->id === $account->id ? 'deactivate' : 'activate' }}"><input type="hidden" name="account_id" value="{{ $account->id }}"><input type="hidden" name="revision" value="{{ $accountSettings->revision }}">
<label>Reason<textarea name="reason" minlength="3" maxlength="1000" required></textarea></label><button class="btn">{{ $activeAccount?->id === $account->id ? 'Deactivate' : 'Activate' }}</button></form></details>
</article>
@endforeach
<details><summary>Receiving-account audit history</summary>
@forelse($accountEvents as $event)<p>{{ $event->created_at }} / {{ ucfirst($event->action) }} / Actor #{{ $event->actor_id }} / Revision {{ $event->revision }}<br>Account {{ $event->account_id }}<br>{{ $event->reason }}</p>@empty<p>No account changes recorded.</p>@endforelse
{{ $accountEvents->links() }}</details>
@endif
