@php
 $receivedEvidence = \Illuminate\Support\Facades\DB::table('gcash_purchase_history')->where('purchase_id',$p->id)->whereIn('event',['admin_received_proof','admin_refund_proof','admin_rejection_refund_proof'])->orderBy('id')->get();
 $updates = $proofs->sortBy('created_at')->slice(1);
 $evidenceLeft = $receivedEvidence;
 $reviewNotes = \Illuminate\Support\Facades\DB::table('gcash_purchase_history')->where('purchase_id',$p->id)->whereIn('event',['admin_short_payment','admin_verified','admin_rejected','overpayment_refunded'])->orderBy('id')->get();
 $linkedRejections = $receivedEvidence->where('event','admin_rejection_refund_proof')->map(function ($proof) use ($reviewNotes) {
  return $reviewNotes->last(fn($note) => $note->event === 'admin_rejected' && $note->id < $proof->id && $note->actor_id == $proof->actor_id)?->id;
 })->filter();
 $notesLeft = $reviewNotes->where('event','admin_rejected')->reject(fn($note) => $linkedRejections->contains($note->id));
@endphp
@foreach($updates as $updatedReceipt)
 @php
  // Keep each review's evidence with the next receipt submitted in response.
  $related = $evidenceLeft->filter(fn($row) => $row->created_at <= $updatedReceipt->created_at);
  $evidenceLeft = $evidenceLeft->reject(fn($row) => $related->contains('id',$row->id));
  $relatedNotes = $notesLeft->filter(fn($note) => $note->created_at <= $updatedReceipt->created_at);
  $notesLeft = $notesLeft->reject(fn($note) => $relatedNotes->contains('id',$note->id));
 @endphp
 <div class="gcash-related-proof-row{{ $related->isEmpty() ? ' is-single-proof' : '' }}">
  @include('gcash.partials.proof-review-notes',['noteEvidence'=>$related,'standaloneNotes'=>$relatedNotes])
  @if($related->isNotEmpty())
  <div class="gcash-related-proof-column">
   @foreach($related as $evidence)
    @include('gcash.partials.admin-evidence-card')
   @endforeach
  </div>
  @endif
  <div class="gcash-related-proof-column">
   @include('gcash.partials.proof-card',['proofLabel'=>'Updated submitted receipt '.$loop->iteration,'proofUrl'=>route('gcash.receipt',[$p->id,$updatedReceipt->id]),'proofMime'=>$updatedReceipt->mime,'proofDate'=>$updatedReceipt->created_at])
  </div>
 </div>
@endforeach
@if($evidenceLeft->isNotEmpty() || $notesLeft->isNotEmpty() || ($pendingUpload ?? false))
 <div class="gcash-related-proof-row{{ !($pendingUpload ?? false) || $evidenceLeft->isEmpty() ? ' is-single-proof' : '' }}">
  @include('gcash.partials.proof-review-notes',['noteEvidence'=>$evidenceLeft,'standaloneNotes'=>$notesLeft])
  @if($evidenceLeft->isNotEmpty())
  <div class="gcash-related-proof-column">
  @foreach($evidenceLeft as $evidence)
   @include('gcash.partials.admin-evidence-card')
  @endforeach
  </div>
  @endif
  @if($pendingUpload ?? false)
   <div class="gcash-related-proof-column order-payment-upload-column">
    @include('buyer.partials.order-payment-upload')
   </div>
  @endif
 </div>
@endif
