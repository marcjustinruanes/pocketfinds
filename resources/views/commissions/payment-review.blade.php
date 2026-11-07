@if($actor->is_admin && in_array($p->status,['pending','review','rejected']))
<details class="commission-action"><summary>Review receipt</summary>
<form method="POST" action="{{ route('commissions.review',$p->id) }}" data-confirm="Record this platform receipt decision?">@csrf
<input type="hidden" name="revision" value="{{ $p->revision }}">
<label>Decision<select name="decision"><option value="review">Flag for further review</option><option value="correction">Request corrections</option><option value="reject">{{ $p->method==='cod'?'Reject proof (keep funds in transit)':'Reject unverified receipt and release allocations' }}</option><option value="verify">Verify actual platform receipt</option></select></label>
<label>Actual received amount PHP<input name="amount" type="number" min="0.01" step="0.01"></label>
<label>Actual transaction reference<input name="reference" maxlength="64"></label>
<label>Review reason / message<textarea name="reason" minlength="3" maxlength="2000" required></textarea></label>
<label><input type="checkbox" name="received" value="1"> I verified actual receipt into the recorded platform account. A message or proof alone does not verify payment.</label>
<button class="btn" type="submit">Record review</button></form></details>
@endif
